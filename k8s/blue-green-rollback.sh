#!/bin/bash

setup_kubectl_context() {
    local env=$1
    
    if [ "$env" == "local" ]; then
        kubectl config use-context minikube
    elif [ "$env" == "aws" ]; then
        local cluster_name=$(grep '^cluster_name=' "$DEPLOYED_AWS_RESOURCES_FILE" | cut -d'=' -f2)
        local aws_region=$(grep '^aws_region=' "$DEPLOYED_AWS_RESOURCES_FILE" | cut -d'=' -f2)
        aws eks --region "$aws_region" update-kubeconfig --name "$cluster_name"
    fi
}

disable_monitoring() {
    local env=$1
    
    log_warn "Eliminando recursos de monitorización para poder llevar a cabo la prueba..."
    kubectl delete -k "$SCRIPT_DIR/$OVERLAYS_PATH""$env/observability/" --ignore-not-found=true 2>/dev/null
    log_info "✓ Recursos de monitorización eliminados."
}

create_green_deployment() {
    local env=$1
    local version=$2
    local strategy=$3
    local deployment_file="$SCRIPT_DIR/$OVERLAYS_PATH""$env/application/deployments/deployment-symfony-green.yaml"
    local original_file="$SCRIPT_DIR/$OVERLAYS_PATH""$env/application/deployments/deployment-symfony.yaml"
    
    # Copiar deployment original y modificarlo a GREEN
    cp "$original_file" "$deployment_file"
    
    # Modificar labels y variables de entorno a GREEN
    sed -i "s/name: symfony-deployment/name: symfony-deployment-green/g" "$deployment_file"
    sed -i "s/version: v1/version: $version/g" "$deployment_file"
    sed -i "s/value: blue.*$/value: green/g" "$deployment_file"
    sed -i "s/value: \"Blue Deployment v1.0\".*$/value: \"Green Deployment $version.0\"/g" "$deployment_file"
    sed -i "s/value: \"1.0.0\".*$/value: \"2.0.0\"/g" "$deployment_file"

    if [ "$strategy" == "contract" ]; then
        sed -i "s/image: archipepe\/mysymfony-php-nginx:7.3-expand-blue-prod/image: archipepe\/mysymfony-php-nginx:7.4-contract-green-prod/g" "$deployment_file"
    fi
    
    kubectl apply -f "$deployment_file"
}

wait_for_green_pods() {
    local version=$1

    if kubectl wait --for=condition=ready pod -l version=$version -n "$SYMFONY_NAMESPACE_NAME" --timeout=300s 2>/dev/null; then
        log_info "✓ Pods listos en $SYMFONY_NAMESPACE_NAME"
        return 0
    else
        log_warn "Timeout: Los pods no están listos en $SYMFONY_NAMESPACE_NAME"
        return 1
    fi
}

get_pod() {
    local version=$1
    kubectl get pods -n "$SYMFONY_NAMESPACE_NAME" -l version=$version -o jsonpath='{.items[0].metadata.name}' 2>/dev/null
}

test_pod_endpoint() {
    local env=$1
    local pod=$2
    local endpoint=$3
    local expected_code=${4:-200}
    
    if [ -z "$pod" ]; then
        return 1
    fi
    
    local status=$(kubectl exec -n "$SYMFONY_NAMESPACE_NAME" "$pod" -- \
        curl -s -o /dev/null -w "%{http_code}" "http://localhost$endpoint" 2>/dev/null || echo "000")
    
    [ "$status" == "$expected_code" ]
}

test_green_health() {
    local env=$1
    local version=$2
    local green_pod
    
    green_pod=$(get_pod "$version")
    
    if test_pod_endpoint "$env" "$green_pod" "/health" 200; then
        return 0
    else
        log_warn "Health status: KO"
        return 1
    fi
}

handle_green_deployment_success() {
    local env=$1
    local version=$2
    
    echo ""
    while true; do
        read -p "¿Cambiar el tráfico al GREEN deployment? (s/n): " -n 1 -r
        echo ""

        if [[ $REPLY =~ ^[Ss]$ ]]; then
            switch_service_to_green "$env" "$version"
            sleep 5
            log_info "✓ Tráfico cambiado a GREEN."
            
            wait_and_test_monitoring "$env"
            
            if test_green_error "$env" "$version"; then
                handle_green_deployment_error "$env"
            else
                log_info "✓ No se detectaron errores en GREEN."
            fi
            
            log_info "Para terminar de limpiar, ejecuta:"
            log_info "  ./deploy-k8s.sh cleanup $env"
            break
        elif [[ $REPLY =~ ^[Nn]$ ]]; then
            log_info "Eliminando GREEN deployment sin cambiar tráfico."
            delete_green_deployment "$env"
            log_info "Para terminar de limpiar, ejecuta:"
            log_info "  ./deploy-k8s.sh cleanup $env"
            break
        else
            echo "Por favor, responde 's' o 'n'."
        fi
    done
}

handle_green_deployment_success_to_contract() {
    local env=$1
    local version=$2
    
    echo ""
    while true; do
        read -p "¿Cambiar el tráfico al GREEN deployment? (s/n): " -n 1 -r
        echo ""

        if [[ $REPLY =~ ^[Ss]$ ]]; then
            switch_service_to_green "$env" "$version"
            sleep 5
            log_info "✓ Tráfico cambiado a GREEN."


            echo ""
            while true; do
                read -p "¿Cambiar el tráfico al BLUE deployment? (s): " -n 1 -r
                echo ""

                if [[ $REPLY =~ ^[Ss]$ ]]; then
                    rollback_to_blue "$env"
                    sleep 5
                    log_info "✓ Tráfico cambiado a BLUE."
                    break
                else
                    echo "Por favor, responde 's'."
                fi
            done

            echo ""
            while true; do
                read -p "¿Cambiar el tráfico al GREEN deployment? (s): " -n 1 -r
                echo ""

                if [[ $REPLY =~ ^[Ss]$ ]]; then
                    switch_service_to_green "$env" "$version"
                    sleep 5
                    log_info "✓ Tráfico cambiado a GREEN."
                    break
                else
                    echo "Por favor, responde 's'."
                fi
            done

            echo ""
            while true; do
                read -p "¿Ejecutar migraciones CONTRACT? (s): " -n 1 -r
                echo ""

                if [[ $REPLY =~ ^[Ss]$ ]]; then
                    local migration_version="DoctrineMigrations\Version20260401100001"
                    run_database_migrations "v1" "$migration_version"
                    log_info "✓ Migraciones $migration_version ejecutadas correctamente."
                    break
                else
                    echo "Por favor, responde 's'."
                fi
            done

            # Eliminamos los BLUE deployments para forzar que el tráfico se quede en GREEN
            delete_blue_deployment "$env"
            
            log_info "Para terminar de limpiar, ejecuta:"
            log_info "  ./deploy-k8s.sh cleanup $env"
            break
        elif [[ $REPLY =~ ^[Nn]$ ]]; then
            log_info "Eliminando GREEN deployment sin cambiar tráfico."
            delete_green_deployment "$env"
            log_info "Para terminar de limpiar, ejecuta:"
            log_info "  ./deploy-k8s.sh cleanup $env"
            break
        else
            echo "Por favor, responde 's' o 'n'."
        fi
    done
}

test_green_error() {
    local env=$1
    local version=$2
    local green_pod
    
    green_pod=$(get_pod "$version")
    
    if test_pod_endpoint "$env" "$green_pod" "/error-test" 500; then
        return 0  # Error encontrado: rollback
    else
        return 1  # Sin errores
    fi
}

switch_service_to_green() {
    local env=$1
    local version=$2

    log_info "Cambiando tráfico a GREEN ($version)..."

    if kubectl patch service nginx-service -n "$SYMFONY_NAMESPACE_NAME" --type='merge' \
        -p="{\"spec\": {\"selector\": {\"version\": \"$version\"}}}" 2>/dev/null; then
        log_info "✓ Servicio apuntando a GREEN ($version)"
        return 0
    else
        log_error "Error al cambiar selector del servicio a $version."
        return 1
    fi
}

wait_and_test_monitoring() {
    log_warn "Esperando 30 segundos antes de probar con /error-test para forzar un rollback automático..."
    log_info "¡Puedes probar a actualizar la página para ver que los GREEN pods están sirviendo!"
    for i in {15..1}; do
        echo -ne "\r$i segundos restantes... "
        sleep 1
    done
    echo ""
}

handle_green_deployment_error() {
    local env=$1
    
    log_warn "GREEN deployment tiene errores. Ejecutando rollback..."
    rollback_to_blue "$env"
    sleep 5
    log_info "✓ Rollback completado. Tráfico devuelto a BLUE."
    delete_green_deployment "$env"
}

rollback_to_blue() {
    local env=$1
    local version=v1
    
    log_info "Revertiendo tráfico a BLUE ($version)..."
    
    if kubectl patch service nginx-service -n "$SYMFONY_NAMESPACE_NAME" --type='merge' \
        -p="{\"spec\": {\"selector\": {\"version\": \"$version\"}}}" 2>/dev/null; then
        log_info "✓ Servicio apuntando a BLUE ($version)"
        return 0
    else
        log_error "Error al cambiar selector del servicio a $version."
        return 1
    fi
}

delete_green_deployment() {
    local env=$1
    
    local deployment_file="$SCRIPT_DIR/$OVERLAYS_PATH""$env/application/deployments/deployment-symfony-green.yaml"
    
    log_info "Eliminando GREEN deployment..."
    
    if [ -f "$deployment_file" ]; then
        kubectl delete -f "$deployment_file" --ignore-not-found=true 2>/dev/null
        rm -f "$deployment_file"
        log_info "✓ GREEN deployment eliminado."
    fi
}

delete_blue_deployment() {
    local env=$1
    
    local deployment_file="$SCRIPT_DIR/$OVERLAYS_PATH""$env/application/deployments/deployment-symfony.yaml"
    
    log_info "Eliminando BLUE deployment..."    
    
    if [ -f "$deployment_file" ]; then
        kubectl delete -f "$deployment_file" --ignore-not-found=true 2>/dev/null
        log_info "✓ BLUE deployment eliminado."
    fi
}

# ==================== MIGRACIONES DE BD (EXPAND PHASE) ====================
run_database_migrations() {
    local version=$1
    local migration_version=$2
    
    log_info "Ejecutando migraciones..."
    
    # Ejecutar migraciones
    if [ "$migration_version" == "" ]; then
        kubectl exec -it -n "$SYMFONY_NAMESPACE_NAME" deployments/symfony-deployment -- \
            php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>/dev/null
    else
        kubectl exec -it -n "$SYMFONY_NAMESPACE_NAME" deployments/symfony-deployment -- \
            php bin/console doctrine:migrations:migrate --no-interaction "$migration_version" 2>/dev/null
    fi    
    
    if [ $? -ne 0 ]; then
        log_error "❌ Las migraciones fallaron."
        return 1
    fi
    
    log_info "✅ Migraciones ejecutadas correctamente."
}

# ==================== PRUEBA DE BLUE/GREEN DEPLOYMENT ====================
test_blue_green_deployment() {
    local env=$1
    
    log_info "================================"
    log_info "PRUEBA BLUE/GREEN DEPLOYMENT"
    log_info "================================"

    setup_kubectl_context "$env"
    disable_monitoring "$env"

    echo ""
    while true; do
        echo "¿Qué estrategia deseas seguir para el despliegue?"
        echo "1) CONTRACT ONLY (MALA PRÁCTICA)"
        echo "2) EXPAND AND CONTRACT (RECOMENDADA)"

        read -p "Elige una opción (1 o 2): " -n 1 -r
        echo ""

        if [[ $REPLY =~ ^[1]$ ]]; then
            run_database_migrations "v1" ""
            log_info "✓ Migraciones ejecutadas correctamente."

            log_info "Creando GREEN deployment..."
            local version_green=v2
            create_green_deployment "$env" "$version_green" ""
            sleep 5

            log_info "Esperando a que los GREEN pods estén listos..."
            wait_for_green_pods "$version_green"
            
            log_info "Probando la salud del GREEN deployment..."
            if test_green_health "$env" "$version_green"; then
                log_info "✓ Green deployment está healthy."
                handle_green_deployment_success "$env" "$version_green"
            else
                log_error "GREEN deployment no está healthy. Eliminando..."
                delete_green_deployment "$env"
                log_info "Para terminar de limpiar, ejecuta:"
                log_info "  ./deploy-k8s.sh cleanup $env"
            fi

            break
        elif [[ $REPLY =~ ^[2]$ ]]; then
            local migration_version="DoctrineMigrations\Version20260401100000"
            run_database_migrations "v1" "$migration_version"
            log_info "✓ Migraciones $migration_version ejecutadas correctamente."

            log_info "Creando GREEN deployment..."
            local version_green=v2
            create_green_deployment "$env" "$version_green" "contract"
            sleep 5
            
            log_info "Esperando a que los GREEN pods estén listos..."
            wait_for_green_pods "$version_green"
            
            log_info "Probando la salud del GREEN deployment..."
            if test_green_health "$env" "$version_green"; then
                log_info "✓ Green deployment está healthy."
                handle_green_deployment_success_to_contract "$env" "$version_green"
            else
                log_error "GREEN deployment no está healthy. Eliminando..."
                delete_green_deployment "$env"
                log_info "Para terminar de limpiar, ejecuta:"
                log_info "  ./deploy-k8s.sh cleanup $env"
            fi

            break
        else
            echo "Por favor, responde '1' o '2'."
        fi
    done
}
