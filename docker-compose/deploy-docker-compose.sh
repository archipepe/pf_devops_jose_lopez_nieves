#!/bin/bash

# Run chmod +x deploy-docker-compose.sh to make this script executable
# Then, run ./deploy-docker-compose.sh to deploy the application in Docker

source ./common-docker-compose.sh

modificar_dockerfiles() {
    if [ "$APP_IMAGE_TYPE" == "$PROD_TYPE" ]; then
        sed -i 's/^FROM .*/FROM '"$REGISTRY"'\/'"$SYMFONY_UBUNTU_BASE_PROD_IMAGE"'/' "$SYMFONY_APP_IMAGE_DOCKERFILE"
    else
        sed -i 's/^FROM .*/FROM '"$REGISTRY"'\/'"$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE"'/' "$SYMFONY_APP_IMAGE_DOCKERFILE"
    fi

    sed -i 's/^FROM .*/FROM '"$REGISTRY"'\/'"$SYMFONY_UBUNTU_BASE_PROD_IMAGE"'/' "$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_DOCKERFILE"
}

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

create_vscodeserver_directory() {
    log_info "Creando directorio .vscode-server del Dev Container..."
    mkdir -p "$SYMFONY_APP_SOURCE_CODE_PATH"".vscode-server"
}

change_permissions() {
    log_info "Changing permissions of the app directory..."
    sudo chown -R $USER:$USER "$SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH"symfony-app && sudo chmod -R 777 "$SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH"symfony-app

    log_info "Changing permissions of tempo-data directory..."
    sudo chown -R $USER:$USER "$TEMPO_DATA_PATH" && sudo chmod -R 777 "$TEMPO_DATA_PATH"

    chmod +x ./cleanup-docker-compose.sh
}

modificar_dockerfiles

build_images

create_vscodeserver_directory

change_permissions

start_docker_containers

install_vendor_dependencies

change_permissions

review_images

review_networks
