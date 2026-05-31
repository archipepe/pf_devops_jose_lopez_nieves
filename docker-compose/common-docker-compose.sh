#!/bin/bash

# Colores para la salida
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # Sin color

# No olvidar export en las variables externas al script
export REGISTRY="mysymfony"
export PROD_TYPE="prod"
export DEBUG_TYPE="debug"

################ CONFIGURACIÓN DE VERSIONES ################
BASE_IMAGE_VERSION="5.1"
APP_IMAGE_VERSION="7.4-contract-green"
export APP_IMAGE_TYPE="$PROD_TYPE"
############################################################

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

export SYMFONY_APP_SOURCE_CODE_PATH="$SYMFONY_APP_IMAGE_PATH""symfony-app/"

# tempo-data
export TEMPO_DATA_PATH="monitoring/tempo/tempo-data"
# .vscode-server
export VSCODE_SERVER_PATH="$SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH""symfony-app/.vscode-server"

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

verify_commands() {
    for cmd in docker; do
        command -v $cmd >/dev/null 2>&1 || { echo "$cmd no instalado."; exit 1; }
    done
}

start_docker_service() {
    log_info "Iniciando Docker..."
    sudo systemctl start docker.service
    log_info "✓ Docker iniciado correctamente."
}

verify_commands

start_docker_service
