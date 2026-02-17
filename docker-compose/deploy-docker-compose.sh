#!/bin/bash

# Run chmod +x deploy-docker-compose.sh to make this script executable
# Then, run ./deploy-docker-compose.sh to deploy the application in Docker

source ./common-docker-compose.sh

build_php_base_image() {
    if [[ "$(docker images -q mysymfony/php:8.2-fpm-1.0 2>/dev/null)" == "" ]]; then
        log_info "Building mysymfony/php:8.2-fpm-1.0 image..."
        docker build -t mysymfony/php:8.2-fpm-1.0 -f ../symfony-php/Dockerfile.base ../symfony-php/
    else
        log_info "Image mysymfony/php:8.2-fpm-1.0 already exists. Skipping build."
    fi
}

start_docker_containers() {
    log_info "Starting Docker containers..."
    docker compose up -d #--build
}

install_vendor_dependencies() {
    log_info "Installing vendor dependencies inside the symfony-php container..."
    # Instalar vendor en /var/www/html para que se refleje en el host
    docker compose exec symfony-php-service composer install --optimize-autoloader --working-dir=/var/www/html
}

change_permissions() {
    log_info "Changing permissions of the app directory to www-data..."
    docker compose exec symfony-php-service chown -R www-data:www-data /var/www/html/var
    docker compose exec symfony-php-service chown -R www-data:www-data /var/www/html/public
    docker compose exec symfony-php-service chmod -R 777 /var/www/html
}

build_php_base_image

start_docker_containers

install_vendor_dependencies

change_permissions

review_images

review_networks
