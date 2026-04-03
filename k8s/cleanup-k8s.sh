#!/bin/bash

# Run chmod +x cleanup-k8s.sh to make this script executable
# Then, run ./cleanup-k8s.sh to clean up all Kubernetes resources in Minikube

source common-k8s.sh

review_hosts() {
    log_info "Revisa el archivo /etc/hosts de tu máquina local y elimina la línea que apunta a symfony.local si existe."
    log_info "Puedes usar el siguiente comando para editar el archivo con permisos de administrador:"
    log_info "sudo nano /etc/hosts"
    cat /etc/hosts
}

review_volumes() {
    log_info "Listado de volúmenes de Minikube:"
    for namespace_name in ${NAMESPACES_NAMES[@]}; do
        log_info "PersistentVolumeClaims en el namespace $namespace_name:"
        kubectl get pvc -n $namespace_name
        log_info "Elimina los volumeclaims mediante:"
        log_info "kubectl delete pvc mysql-pvc -n $namespace_name"
        echo ""
        log_info "Para asegurarte bien, entra con minikube ssh y revisa el directorio /tmp/hostpath-provisioner/$namespace_name para eliminar los volúmenes que ya no necesites."
    done

    echo ""
    log_info "PersistentVolume (namespace independent):"
    kubectl get pv
    log_info "Elimina los volúmenes mediante (no hay que poner el namespace):"
    log_info "kubectl delete pv pvc-96760041-2b17-43c5-9a47-5f83acd2a5bf"
}

review_k8s_resources() {
    review_volumes
    review_hosts
}

delete_k8s_local_resources() {
    log_info "Eliminando recursos en Kubernetes con kustomization..."
    kubectl delete -k "$KUSTOMIZATION_LOCAL_PATH"
}

delete_k8s_local_resources
review_k8s_resources
review_images

log_info "Limpieza completada!"
