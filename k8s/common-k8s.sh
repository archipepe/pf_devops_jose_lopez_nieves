#!/bin/bash

# Configuration
SYMFONY_NAMESPACE_NAME="symfony-ns"
export NAMESPACES_NAMES=($SYMFONY_NAMESPACE_NAME)

SYMFONY_NAMESPACE="namespaces/namespace-symfony.yaml"
export NAMESPACES=($SYMFONY_NAMESPACE)

CONFIGMAP_MYSQL="configmaps/configmap-mysql.yaml"
export CONFIGMAPS=($CONFIGMAP_MYSQL)

DEPLOYMENT_MYSQL="deployments/deployment-mysql.yaml"
DEPLOYMENT_SYMFONY="deployments/deployment-symfony.yaml"
SERVICE_MYSQL="services/service-mysql.yaml"
SERVICE_NGINX="services/service-nginx.yaml"
export DEPLOYMENT_ORDER=($DEPLOYMENT_MYSQL $SERVICE_MYSQL $DEPLOYMENT_SYMFONY $SERVICE_NGINX)

export INGRESS_SYMFONY="ingress/ingress-symfony.yaml"
export INGRESS_HOST="symfony.local"

VOLUMECLAIMS_MYSQL="volumes/pvc-mysql.yaml"
VOLUMECLAIMS_SYMFONY="volumes/pvc-symfony.yaml"
export VOLUMECLAIMS=($VOLUMECLAIMS_MYSQL $VOLUMECLAIMS_SYMFONY)

MYSQL_SECRET="secrets/secret-mysql.yaml"
SYMFONY_ENV="secrets/secret-symfony.yaml"
export SECRETS=($MYSQL_SECRET $SYMFONY_ENV)


# export must be in all variables
export REGISTRY="mysymfony"
export SYMFONY_PHP_BASE_IMAGE_PATH="../symfony-php/"
export SYMFONY_PHP_BASE_IMAGE="php:8.2-fpm-1.0"
export SYMFONY_PHP_BASE_IMAGE_DOCKERFILE="../symfony-php/Dockerfile.base"
export SYMFONY_PHP_IMAGE_PATH="../symfony-php/"
export SYMFONY_PHP_IMAGE="symfony-php:2.0"
export SYMFONY_PHP_IMAGE_DOCKERFILE="../symfony-php/Dockerfile.app"
export SYMFONY_NGINX_IMAGE_PATH="../symfony-nginx/"
export SYMFONY_NGINX_IMAGE="symfony-nginx:1.0"
export SYMFONY_NGINX_IMAGE_DOCKERFILE="../symfony-nginx/Dockerfile"
export IMAGES=("SYMFONY_PHP_BASE" "SYMFONY_PHP" "SYMFONY_NGINX")

# Colours for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging functions
log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# Declare kubectl function to use minikube's kubectl
kubectl() {
    minikube kubectl -- "$@"
    # alias kubectl="minikube kubectl --"
}

review_images() {
    log_info "Listado de imágenes de Minikube:"
    minikube image ls
    log_info "Revisa imágenes antiguas que puedas querer eliminar mediante:"
    log_info "minikube image rm docker.io/$REGISTRY/symfony-php:X.X"
    log_info "minikube image rm docker.io/library/mysql:X.X"
}

enable_addons() {
    log_info "Habilitando addons de Minikube..."
    minikube addons enable ingress
    minikube addons enable default-storageclass
    minikube addons enable storage-provisioner
}

verify_commands() {
    for cmd in docker minikube; do
        command -v $cmd >/dev/null 2>&1 || { echo "$cmd not installed."; exit 1; }
    done
}

start_docker_service() {
    sudo systemctl start docker.service
}

start_minikube() {
    if minikube status | grep -q "host: Stopped"; then
        log_info "Starting Minikube..."
        minikube start
    else
        log_info "Minikube is already running."
    fi
}

verify_commands
start_docker_service
start_minikube
