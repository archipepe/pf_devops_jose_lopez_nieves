#!/bin/bash

# Run chmod +x cleanup-k8s.sh to make this script executable
# Then, run ./cleanup-k8s.sh to clean up all Kubernetes resources in Minikube

source common-k8s.sh

delete_all() {
    for namespace_name in ${NAMESPACES_NAMES[@]}; do
        log_info "Eliminando recursos en el namespace $namespace_name..."
        kubectl delete all --all -n $namespace_name
    done
}

delete_ingress() {
    log_info "Eliminando Ingress $INGRESS_SYMFONY..."
    kubectl delete -f "$INGRESS_SYMFONY"
}

delete_secrets() {
    for secret in ${SECRETS[@]}; do
        log_info "Eliminando secret $secret..."
        kubectl delete -f "$secret"
    done
}

delete_configmaps() {
    for configmap in ${CONFIGMAPS[@]}; do
        log_info "Eliminando ConfigMap $configmap..."
        kubectl delete -f "$configmap"
    done
}

delete_namespace() {
    for namespace_name in ${NAMESPACES_NAMES[@]}; do
        log_info "Eliminando namespace $namespace_name..."
        kubectl delete namespace $namespace_name
    done
}

review_hosts() {
    log_info "Revisa el archivo /etc/hosts de tu máquina local y elimina la línea que apunta a symfony.local si existe."
    log_info "Puedes usar el siguiente comando para editar el archivo con permisos de administrador:"
    log_info "sudo nano /etc/hosts"
    cat /etc/hosts
}

review_volumes() {
    log_info "Listado de volúmenes de Minikube:"
    for namespace_name in ${NAMESPACES_NAMES[@]}; do
        log_info "PersistentVolume en el namespace $namespace_name:"
        kubectl get pv -n $namespace_name
        log_info "PersistentVolumeClaims en el namespace $namespace_name:"
        kubectl get pvc -n $namespace_name
    done
}

delete_k8s_resources() {
    delete_all
    delete_ingress
    delete_secrets
    # delete_configmaps
    # delete_namespace # Se comenta para no borrar los volúmenes que ya puedan tener algo de datos
    review_volumes
    review_hosts
}

delete_k8s_resources
review_images

log_info "Limpieza completada!"
