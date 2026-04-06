#!/bin/bash

# Configuration
OVERLAYS_PATH="overlays/"
export KUSTOMIZATION_LOCAL_PATH="$OVERLAYS_PATH""local/"
export KUSTOMIZATION_AWS_PATH="$OVERLAYS_PATH""aws/"

SYMFONY_NAMESPACE_NAME="symfony-ns"
MONITORING_NAMESPACE_NAME="monitoring-ns"
export NAMESPACES_NAMES=($SYMFONY_NAMESPACE_NAME $MONITORING_NAMESPACE_NAME)

export INGRESS_HOST="symfony.local"

# No olvidar export en las variables externas al script
export REGISTRY="mysymfony"
export PROD_TYPE="prod"
export DEBUG_TYPE="debug"

################ CONFIGURACIÓN DE VERSIONES ################
BASE_IMAGE_VERSION="5.0"
APP_IMAGE_VERSION="7.0"
export APP_IMAGE_TYPE="$PROD_TYPE"
############################################################

export SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH="../php-nginx/"
export SYMFONY_UBUNTU_BASE_PROD_IMAGE="ubuntu:24.04-""$BASE_IMAGE_VERSION""-""$PROD_TYPE"
export SYMFONY_UBUNTU_BASE_PROD_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.base.prod"
export SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_PATH="../php-nginx/"
export SYMFONY_UBUNTU_BASE_DEBUG_IMAGE="ubuntu:24.04-""$BASE_IMAGE_VERSION""-""$DEBUG_TYPE"
export SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.base.debug"
export SYMFONY_APP_IMAGE_PATH="../php-nginx/"
export SYMFONY_APP_IMAGE="php-nginx:""$APP_IMAGE_VERSION""-""$APP_IMAGE_TYPE"
export SYMFONY_APP_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.app"
export IMAGES=("SYMFONY_UBUNTU_BASE_PROD" "SYMFONY_UBUNTU_BASE_DEBUG" "SYMFONY_APP")

export DEPLOYMENT_SYMFONY_LOCAL_PATH="overlays/local/application/deployments/deployment-symfony.yaml"
export DEPLOYMENT_SYMFONY_AWS_PATH="overlays/aws/application/deployments/deployment-symfony.yaml"

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
    # TODO: mejorar. Hay que esperar en la primera ejecución con kustomization, pero no en las sucesivas
    # log_info "Verificando servicios... (esperando 45 segundos)..."
    # sleep 45
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
