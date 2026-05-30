#!/bin/bash

export PROD_TYPE="prod"
export DEBUG_TYPE="debug"

################ CONFIGURACIÓN DE VERSIONES ################
BASE_IMAGE_VERSION="5.1"
APP_IMAGE_VERSION="7.1"
export APP_IMAGE_TYPE="$PROD_TYPE"
############################################################

# Configuración
export OVERLAYS_PATH="overlays/"
export KUSTOMIZATION_LOCAL_PATH="$OVERLAYS_PATH""local/"
export KUSTOMIZATION_AWS_PATH="$OVERLAYS_PATH""aws/"

export SYMFONY_NAMESPACE_NAME="symfony-ns"
MONITORING_NAMESPACE_NAME="monitoring-ns"
export NAMESPACES_NAMES=($SYMFONY_NAMESPACE_NAME)

export INGRESS_HOST="symfony.local"

export REGISTRY="mysymfony"

export DOCKER_ACCOUNT="archipepe"

export SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH="../php-nginx/"
export SYMFONY_UBUNTU_BASE_PROD_IMAGE_NAME="ubuntu"
export SYMFONY_UBUNTU_BASE_PROD_IMAGE_TAG="24.04-""$BASE_IMAGE_VERSION""-""$PROD_TYPE"
export SYMFONY_UBUNTU_BASE_PROD_IMAGE="$SYMFONY_UBUNTU_BASE_PROD_IMAGE_NAME"":""$SYMFONY_UBUNTU_BASE_PROD_IMAGE_TAG"
export SYMFONY_UBUNTU_BASE_PROD_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.base.prod"
export SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_PATH="../php-nginx/"
export SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_NAME="ubuntu"
export SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_TAG="24.04-""$BASE_IMAGE_VERSION""-""$DEBUG_TYPE"
export SYMFONY_UBUNTU_BASE_DEBUG_IMAGE="$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_NAME"":""$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_TAG"
export SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.base.debug"
export SYMFONY_APP_IMAGE_PATH="../php-nginx/"
export SYMFONY_APP_IMAGE_NAME="php-nginx"
export SYMFONY_APP_IMAGE_TAG="$APP_IMAGE_VERSION""-""$APP_IMAGE_TYPE"
export SYMFONY_APP_IMAGE="$SYMFONY_APP_IMAGE_NAME"":""$SYMFONY_APP_IMAGE_TAG"
export SYMFONY_APP_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.app"
export IMAGES=("SYMFONY_UBUNTU_BASE_PROD" "SYMFONY_UBUNTU_BASE_DEBUG" "SYMFONY_APP")

export DEPLOYMENT_SYMFONY_LOCAL_PATH="$KUSTOMIZATION_LOCAL_PATH""application/deployments/deployment-symfony.yaml"
export DEPLOYMENT_SYMFONY_AWS_PATH="$KUSTOMIZATION_AWS_PATH""application/deployments/deployment-symfony.yaml"

# tempo-data
export TEMPO_DATA_PATH="../docker-compose/monitoring/tempo/tempo-data"
# .vscode-server
export VSCODE_SERVER_PATH="$SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH""symfony-app/.vscode-server"

# Colores para la salida
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

enable_addons() {
    log_info "Habilitando addons de Minikube..."
    minikube addons enable ingress
    minikube addons enable default-storageclass
    minikube addons enable storage-provisioner
    minikube addons enable metrics-server
    log_info "✓ Addons de Minikube habilitados correctamente."
}

start_docker_service() {
    log_info "Iniciando Docker..."
    sudo systemctl start docker.service
    log_info "✓ Docker iniciado correctamente."
}

start_minikube() {
    if minikube status | grep -q "host: Stopped"; then
        log_info "Iniciando Minikube..."
        minikube start
        kubectl config use-context minikube
        log_info "✓ Minikube iniciado correctamente."
    else
        log_info "✓ Minikube ya está iniciado."
    fi
}
