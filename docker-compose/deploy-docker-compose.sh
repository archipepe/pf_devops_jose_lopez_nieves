#!/bin/bash

# Run chmod +x deploy-docker-compose.sh to make this script executable
# Then, run ./deploy-docker-compose.sh to deploy the application in Docker

source ./common-docker-compose.sh

build_images() {
    log_info "Building Docker images..."
    for image in ${IMAGES[@]}; do
        path_var="${image}_IMAGE_PATH"
        tag_var="${image}_IMAGE"
        dockerfile_var="${image}_IMAGE_DOCKERFILE"
        if [[ "$(docker images -q "$REGISTRY/${!tag_var}" 2>/dev/null)" == "" ]]; then
            log_info "Building ${!tag_var}..."
            docker build -t $REGISTRY/${!tag_var} -f ${!path_var}/${!dockerfile_var} ${!path_var} || log_error "Error building ${!tag_var}"
        else
            log_info "${!tag_var} already exists. Skipping build."
        fi
    done
}

start_docker_containers() {
    log_info "Starting Docker containers..."
    docker compose up -d #--build
}

install_vendor_dependencies() {
    log_info "Installing vendor dependencies inside the symfony-php container..."
    # Instalar vendor en /var/www/html para que se refleje en el host
    docker compose exec symfony-php-nginx-service composer install --optimize-autoloader --working-dir=/var/www/html
}

change_permissions() {
    log_info "Changing permissions of the app directory..."
    sudo chown -R $USER:$USER "$SYMFONY_UBUNTU_BASE_IMAGE_PATH"symfony-app && sudo chmod -R 777 "$SYMFONY_UBUNTU_BASE_IMAGE_PATH"symfony-app

    log_info "Changing permissions of tempo-data directory..."
    sudo chown -R $USER:$USER "$TEMPO_DATA_PATH" && sudo chmod -R 777 "$TEMPO_DATA_PATH"
}

build_images

change_permissions

start_docker_containers

install_vendor_dependencies

change_permissions

review_images

review_networks
