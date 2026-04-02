#!/bin/bash

# Run chmod +x deploy-k8s.sh to make this script executable
# Then, run ./deploy-k8s.sh to deploy the application in Minikube

####################################################################################
# Create ~/.bash_aliases and add this line uncommented to use kubectl with minikube:
# alias kubectl="minikube kubectl --"
####################################################################################

source common-k8s.sh

create_namespaces() {
    for namespace in ${NAMESPACES[@]}; do
        log_info "Checking namespace $namespace..."
        kubectl get -f "$namespace" >/dev/null 2>&1 || {
            log_info "Creating namespace..."
            kubectl apply -f "$namespace"
        }
    done
}

create_secrets() {
    for secret in ${SECRETS[@]}; do
        log_info "Creating secret $secret..."
        kubectl apply -f "$secret"
    done
}

create_configmaps() {
    for configmap in ${CONFIGMAPS[@]}; do
        log_info "Creating ConfigMap $configmap..."
        kubectl apply -f "$configmap"
    done
}

create_volume_claims() {
    for pvc in ${VOLUMECLAIMS[@]}; do
        log_info "Creating PersistentVolumeClaim $pvc..."
        kubectl apply -f "$pvc"
    done
}

apply_deployments_and_services() {
    for file in ${DEPLOYMENT_ORDER[@]}; do
        if [ -f "$file" ]; then
            log_info "Applying $file..."
            kubectl apply -f "$file"
        else
            log_warn "Archivo "$file" no encontrado"
        fi
    done
}

create_ingress() {
    log_info "Intentando aplicar el Ingress $INGRESS_SYMFONY..."

    local MAX_RETRIES=10
    local RETRY_DELAY=10
    local COUNT=1

    until kubectl apply -f "$INGRESS_SYMFONY"; do
        if [ $COUNT -ge $MAX_RETRIES ]; then
            log_error "No se pudo aplicar el Ingress después de $MAX_RETRIES intentos"
            exit 1
        fi

        log_warn "El webhook aún no está listo. Reintentando en $RETRY_DELAY segundos... ($COUNT/$MAX_RETRIES)"
        sleep $RETRY_DELAY
        COUNT=$((COUNT+1))
    done

    log_info "Ingress aplicado correctamente"
}

apply_k8s_resources() {
    log_info "Desplegando en Kubernetes..."
    create_namespaces
    create_secrets
    create_configmaps
    create_volume_claims
    apply_deployments_and_services
    create_ingress
}

apply_kustomization() {
    log_info "Desplegando en Kubernetes con kustomization..."
    kubectl apply -k application/
}

apply_kustomization_monitoring() {
    log_info "Desplegando en Kubernetes con kustomization observability..."
    kubectl apply -k observability/
}

# Build images if they don't exist in Minikube's registry and send them to Minikube
build_images() {
    log_info "Building Docker images for Minikube..."
    eval $(minikube docker-env)
    for image in ${IMAGES[@]}; do
        path_var="${image}_IMAGE_PATH"
        tag_var="${image}_IMAGE"
        dockerfile_var="${image}_IMAGE_DOCKERFILE"
        if ! minikube image ls | grep -q "$REGISTRY/${!tag_var}"; then
            log_info "Building ${!tag_var}..."
            docker build -t $REGISTRY/${!tag_var} -f ${!path_var}/${!dockerfile_var} ${!path_var} || log_error "Error building ${!tag_var}"
        else
            log_info "${!tag_var} already exists in Minikube registry."
        fi
    done
}

verify_services() {
    log_info "Verificando despliegue... (esperando 15 segundos)"
    sleep 15  # Esperar a que los pods inicien
    
    for namespace_name in ${NAMESPACES_NAMES[@]}; do    
        log_info "Pods en el namespace $namespace_name:"
        kubectl get pods -n $namespace_name

        log_info "Servicios en el namespace $namespace_name:"
        kubectl get services -n $namespace_name

        log_info "Ingress en el namespace $namespace_name:"
        kubectl get ingress -n $namespace_name
        
        log_info "Accede a la aplicación Symfony con:"
        minikube service list -n $namespace_name
    done
}

add_ingress_to_hosts() {
    if grep -q "$INGRESS_HOST" /etc/hosts; then
        log_info "El host '$INGRESS_HOST' ya está configurado en /etc/hosts"
    else
        MINIKUBE_IP=$(minikube ip)
        log_info "Añadiendo $INGRESS_HOST -> $MINIKUBE_IP a /etc/hosts"
        echo "$MINIKUBE_IP    $INGRESS_HOST" | sudo tee -a /etc/hosts > /dev/null
        log_info "Host añadido correctamente"
    fi

    if grep -q "$INGRESS_GRAFANA_HOST" /etc/hosts; then
        log_info "El host '$INGRESS_GRAFANA_HOST' ya está configurado en /etc/hosts"
    else
        MINIKUBE_IP=$(minikube ip)
        log_info "Añadiendo $INGRESS_GRAFANA_HOST -> $MINIKUBE_IP a /etc/hosts"
        echo "$MINIKUBE_IP    $INGRESS_GRAFANA_HOST" | sudo tee -a /etc/hosts > /dev/null
        log_info "Host añadido correctamente"
    fi
}

change_permissions() {
    chmod +x ./cleanup-k8s.sh
}

enable_addons
build_images
# apply_k8s_resources
apply_kustomization
apply_kustomization_monitoring
verify_services
add_ingress_to_hosts
review_images
change_permissions

log_info "Despliegue completado!"
