#!/bin/bash

# Run chmod +x deploy-k8s.sh to make this script executable
# Then, run ./deploy-k8s.sh to deploy the application in Minikube

####################################################################################
# Create ~/.bash_aliases and add this line uncommented to use kubectl with minikube:
# alias kubectl="minikube kubectl --"
####################################################################################

source common-k8s.sh

wait_for_ingress_controller() {
    local max_wait=120
    local elapsed=0
    local namespace="ingress-nginx"
    local selector="app.kubernetes.io/name=ingress-nginx"
    
    log_info "Esperando Ingress Controller..."
    
    while [ $elapsed -lt $max_wait ]; do
        # Método 1: EndpointSlices (K8s 1.21+)
        local ready_endpoints=$(kubectl get endpointslices -n $namespace \
            -l kubernetes.io/service-name=ingress-nginx-controller-admission \
            -o jsonpath='{.items[*].endpoints[*].conditions.ready}' 2>/dev/null | grep -c true)
        
        # Método 2: Pods ready (fallback)
        local ready_pods=$(kubectl get pods -n $namespace -l $selector \
            -o jsonpath='{.items[*].status.conditions[?(@.type=="Ready")].status}' 2>/dev/null | grep -c True)
        
        if [ "$ready_endpoints" -gt 0 ] || [ "$ready_pods" -gt 0 ]; then
            echo ""
            log_info "Ingress Controller listo (endpoints: $ready_endpoints, pods: $ready_pods)"
            sleep 5
            return 0
        fi
        
        echo -n "."
        sleep 2
        elapsed=$((elapsed + 2))
    done
    
    log_error "Ingress Controller no disponible después de ${max_wait}s"
    return 1
}

apply_k8s_local_resources() {
    log_info "Desplegando en Kubernetes con kustomization..."
    kubectl apply -k "$KUSTOMIZATION_LOCAL_PATH"
}

modificar_dockerfiles() {
    if [ "$APP_IMAGE_TYPE" == "$PROD_TYPE" ]; then
        sed -i 's/^FROM .*/FROM '"$REGISTRY"'\/'"$SYMFONY_UBUNTU_BASE_PROD_IMAGE"'/' "$SYMFONY_APP_IMAGE_DOCKERFILE"
    else
        sed -i 's/^FROM .*/FROM '"$REGISTRY"'\/'"$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE"'/' "$SYMFONY_APP_IMAGE_DOCKERFILE"
    fi

    sed -i 's/^FROM .*/FROM '"$REGISTRY"'\/'"$SYMFONY_UBUNTU_BASE_PROD_IMAGE"'/' "$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_DOCKERFILE"

    # TODO: mejorar
    sed -i 's|image: .*|image: '"$REGISTRY/""$SYMFONY_APP_IMAGE"'|' "$DEPLOYMENT_SYMFONY_LOCAL_PATH"
    sed -i 's|image: .*|image: public.ecr.aws/l7n5d2e2/'"$REGISTRY/""$SYMFONY_APP_IMAGE"'|' "$DEPLOYMENT_SYMFONY_AWS_PATH"
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
}

change_permissions() {
    chmod +x ./cleanup-k8s.sh
}

enable_addons
modificar_dockerfiles
build_images
wait_for_ingress_controller
apply_k8s_local_resources
verify_services
add_ingress_to_hosts
review_images
change_permissions

log_info "Despliegue completado!"
