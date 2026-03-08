#!/bin/bash

# Configuration
SYMFONY_NAMESPACE_NAME="symfony-ns"
export NAMESPACES_NAMES=($SYMFONY_NAMESPACE_NAME)

SYMFONY_NAMESPACE="namespaces/namespace-symfony.yaml"
export NAMESPACES=($SYMFONY_NAMESPACE)

CONFIGMAP_MYSQL="configmaps/configmap-mysql.yaml"
export CONFIGMAPS=($CONFIGMAP_MYSQL)

DEPLOYMENT_MYSQL="deployments/local/deployment-mysql.yaml"
DEPLOYMENT_SYMFONY="deployments/local/deployment-symfony.yaml"
SERVICE_MYSQL="services/local/service-mysql.yaml"
SERVICE_NGINX="services/local/service-nginx.yaml"
export DEPLOYMENT_ORDER=($DEPLOYMENT_MYSQL $SERVICE_MYSQL $DEPLOYMENT_SYMFONY $SERVICE_NGINX)

export INGRESS_SYMFONY="ingresses/local/ingress-symfony.yaml"
export INGRESS_HOST="symfony.local"

VOLUMECLAIMS_MYSQL="volumes/local/pvc-mysql.yaml"
VOLUMECLAIMS_SYMFONY="volumes/local/pvc-symfony.yaml"
export VOLUMECLAIMS=($VOLUMECLAIMS_MYSQL $VOLUMECLAIMS_SYMFONY)

MYSQL_SECRET="secrets/secret-mysql.yaml"
SYMFONY_ENV="secrets/secret-symfony.yaml"
export SECRETS=($MYSQL_SECRET $SYMFONY_ENV)

# export must be in all variables
export REGISTRY="mysymfony"
export SYMFONY_UBUNTU_BASE_IMAGE_PATH="../php-nginx/"
export SYMFONY_UBUNTU_BASE_IMAGE="ubuntu:24.04-2.0"
export SYMFONY_UBUNTU_BASE_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.base"
export SYMFONY_APP_IMAGE_PATH="../php-nginx/"
export SYMFONY_APP_IMAGE="php-nginx:4.1"
export SYMFONY_APP_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.app"
export IMAGES=("SYMFONY_UBUNTU_BASE" "SYMFONY_APP")

# Colours for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging functions
log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

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
    for cmd in docker minikube kubectl; do
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
