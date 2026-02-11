#!/bin/bash

# Run chmod +x cleanup-docker-compose.sh to make this script executable
# Then, run ./cleanup-docker-compose.sh to clean up all Docker resources

source ./common-docker-compose.sh

stop_and_remove_containers() {
    log_info "Deteniendo y eliminando contenedores de Docker..."
    docker compose down
}

stop_and_remove_containers

review_images

review_volumes

review_networks
