#!/bin/bash

# Colours for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# export must be in all variables
export REGISTRY="mysymfony"
export SYMFONY_UBUNTU_BASE_IMAGE_PATH="../php-nginx/"
export SYMFONY_UBUNTU_BASE_IMAGE="ubuntu:24.04-4.0-debug"
export SYMFONY_UBUNTU_BASE_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.base"
export SYMFONY_APP_IMAGE_PATH="../php-nginx/"
export SYMFONY_APP_IMAGE="php-nginx:5.0-debug"
export SYMFONY_APP_IMAGE_DOCKERFILE="../php-nginx/Dockerfile.app"
export IMAGES=("SYMFONY_UBUNTU_BASE" "SYMFONY_APP")

# tempo-data
export TEMPO_DATA_PATH="../monitoring/tempo/tempo-data"

# Logging functions
log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

review_images() {
    log_info "Listado de imágenes de Docker:"
    docker images --format table | grep "docker-compose-symfony"
    docker images --format table | grep "$REGISTRY"
    docker images --format table | grep "mysql"
    log_info "Revisa imágenes antiguas que puedas querer eliminar mediante:"
    log_info "docker compose down"
    log_info "docker rmi docker-compose-symfony-nginx:latest"
    log_info "docker rmi ddc417704227"
}

review_volumes() {
    log_info "Listado de volúmenes de Docker:"
    docker volume ls --format table | grep "docker-compose"
    log_info "Revisa volúmenes antiguos que puedas querer eliminar mediante:"
    log_info "docker volume rm docker-compose_mysql_data"
}

review_networks() {
    log_info "Listado de redes de Docker:"
    docker network ls --format table | grep "docker-compose"
    log_info "Revisa redes antiguas que puedas querer eliminar mediante:"
    log_info "docker network rm docker-compose_default"
}

verify_commands() {
    for cmd in docker; do
        command -v $cmd >/dev/null 2>&1 || { echo "$cmd not installed."; exit 1; }
    done
}

start_docker_service() {
    sudo systemctl start docker.service
}

verify_commands
start_docker_service