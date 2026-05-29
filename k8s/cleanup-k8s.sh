#!/bin/bash

remove_ingress_from_hosts() {
    if grep -q "$INGRESS_HOST" /etc/hosts; then
        log_info "Eliminando el host '$INGRESS_HOST' de /etc/hosts"
        
        sudo sed -i "/$INGRESS_HOST/d" /etc/hosts
        sudo sed -i '/^$/d' /etc/hosts
        
        log_info "✓ El host '$INGRESS_HOST' se ha eliminado correctamente de /etc/hosts"
    fi
}

review_images() {
    local images=(
        "cadvisor:v0.49.1"
        "prom/prometheus:v3.5.0"
        "prom/node-exporter:v1.9.1"
        "otel/opentelemetry-collector:0.135.0"
        "mysql:8.0"
        "grafana/tempo:2.8.2"
        "grafana/loki:3.5.5"
        "grafana/grafana:12.1.1"
    )
    
    log_info "Listado de imágenes de Minikube:"
    for img in "${images[@]}"; do
        minikube image ls | grep "$img" || true
    done
    minikube image ls | grep "$DOCKER_ACCOUNT" || true
    
    log_info "Elimina imágenes antiguas mediante:"
    log_info "  minikube image rm IMAGEN"
    
    echo ""
    log_info "Listado de imágenes de Docker:"
    for img in "${images[@]}"; do
        local repo=$(echo "$img" | cut -d':' -f1)
        docker images --format "table {{.Repository}}\t{{.Tag}}\t{{.ID}}\t{{.CreatedSince}}\t{{.Size}}" | grep "$repo" || true
    done
    docker images --format "table {{.Repository}}\t{{.Tag}}\t{{.ID}}\t{{.CreatedSince}}\t{{.Size}}" | grep "$DOCKER_ACCOUNT" || true

    log_info "Elimina imágenes antiguas mediante:"
    log_info "  docker rmi IMAGEN"
}

cleanup_deployment() {
    local env=$1
    
    log_info "================================"
    log_info "LIMPIANDO DESPLIEGUE $env"
    log_info "================================"
    
    if [ "$env" == "local" ]; then
        log_info "Eliminando recursos de Kubernetes (local)..."

        kubectl config use-context minikube

        kubectl delete -k "$KUSTOMIZATION_LOCAL_PATH" --ignore-not-found=true 2>/dev/null

        remove_ingress_from_hosts

        review_images

        rm -f "$DEPLOYED_LOCAL_RESOURCES_FILE"

        log_info "================================"
        log_info "✓ LIMPIEZA COMPLETADA"
        log_info "================================"
    elif [ "$env" == "aws" ]; then
        log_warn "IMPORTANTE: La limpieza de AWS eliminará todos los recursos de este proyecto."
        
        echo ""
        while true; do
            read -p "¿Deseas continuar? (s/n): " -n 1 -r
            echo ""

            if [[ $REPLY =~ ^[Ss]$ ]]; then
                log_info "Eliminando recursos de Kubernetes..."

                local cluster_name=$(grep '^cluster_name=' "$DEPLOYED_AWS_RESOURCES_FILE" | cut -d'=' -f2)
                local aws_region=$(grep '^aws_region=' "$DEPLOYED_AWS_RESOURCES_FILE" | cut -d'=' -f2)
                local context=$(grep '^context=' "$DEPLOYED_AWS_RESOURCES_FILE" | cut -d'=' -f2)

                aws eks --region "$aws_region" update-kubeconfig --name "$cluster_name"

                kubectl delete -k "$KUSTOMIZATION_AWS_PATH" --ignore-not-found=true 2>/dev/null

                log_info "Eliminando infraestructura de Terraform..."
                log_info "IMPORTANTE: Esto puede tardar más de 10 minutos."
                
                cd "$SCRIPT_DIR/../infra/main"
                log_info "Ejecutando: terraform destroy"
                terraform destroy -auto-approve
                cd - > /dev/null

                cd "$SCRIPT_DIR/../infra/bootstrap"
                log_info "Ejecutando: terraform destroy (bootstrap)"
                # terraform destroy -auto-approve
                cd - > /dev/null

                # Limpiar clúster del kubeconfig
                kubectl config use-context minikube

                kubectl config delete-context "$context" && \
                kubectl config delete-cluster "$context" && \
                kubectl config delete-user    "$context"

                log_warn "⚠ ADVERTENCIA: Verifica manualmente en AWS que todos los recursos se hayan eliminado para evitar costes adicionales."
                log_warn "Revisa: VPC, NAT Gateways, Elastic IPs, Security Groups, EKS Node Group, instancias EC2, volúmenes EC2 y EFS..."

                # Limpiar archivos locales
                rm -f "$DEPLOYED_AWS_RESOURCES_FILE"

                log_info "================================"
                log_info "✓ LIMPIEZA COMPLETADA"
                log_info "================================"

                break
            elif [[ $REPLY =~ ^[Nn]$ ]]; then
                log_info "Limpieza cancelada."
                break
            else
                echo "Por favor, responde 's' o 'n'."
            fi
        done
    fi
}
