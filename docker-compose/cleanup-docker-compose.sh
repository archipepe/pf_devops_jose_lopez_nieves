#!/bin/bash

# Ejecuta chmod +x cleanup-docker-compose.sh
# Luego ejecuta ./cleanup-docker-compose.sh para borrar todos los recursos Docker

source ./common-docker-compose.sh

stop_and_remove_containers() {
    log_info "Deteniendo y eliminando contenedores de Docker..."
    docker compose down
    log_info "✓ Contenedores de Docker detenidos y eliminados correctamente."
}

review_images() {
    local images=(
        "cadvisor:v0.49.1"
        "prom/prometheus:v3.5.0"
        "prom/node-exporter:v1.9.1"
        "otel/opentelemetry-collector:0.135.0"
        "mysql:8.0"
        "grafana/tempo:2.8.2"
        "grafana/loki:3.5.5"
        "grafana/grafana:12.1.1"
    )
    
    log_info "Listado de imágenes de Docker:"
    for img in "${images[@]}"; do
        local repo=$(echo "$img" | cut -d':' -f1)
        docker images --format "table {{.Repository}}\t{{.Tag}}\t{{.ID}}\t{{.CreatedSince}}\t{{.Size}}" | grep "$repo" || true
    done
    docker images --format "table {{.Repository}}\t{{.Tag}}\t{{.ID}}\t{{.CreatedSince}}\t{{.Size}}" | grep "$DOCKER_ACCOUNT" || true

    log_info "Elimina imágenes antiguas mediante:"
    log_info "  docker rmi IMAGEN"
}

review_volumes() {
    log_info "Listado de volúmenes de Docker:"
    docker volume ls --format table | grep "docker-compose"
    log_info "Elimina volúmenes antiguos mediante:"
    log_info "  docker volume rm docker-compose_mysql_data"
    log_info "  docker volume rm docker-compose_prometheus_data"
}

stop_and_remove_containers

review_images

review_volumes
