#!/bin/bash

# Ejecuta chmod +x deploy-docker-compose.sh
# Luego ejecuta ./deploy-docker-compose.sh [DOCKER_ACCOUNT] para desplegar la aplicación en Docker

source ./common-docker-compose.sh

# Permitir sobrescribir DOCKER_ACCOUNT como primer argumento
if [ $# -ge 1 ]; then
    export DOCKER_ACCOUNT="$1"
fi

modify_dockerfiles() {
    if [ "$APP_IMAGE_TYPE" == "$PROD_TYPE" ]; then
        sed -i 's/^FROM .*/FROM '"$DOCKER_ACCOUNT"'\/'"$REGISTRY"'-'"$SYMFONY_UBUNTU_BASE_PROD_IMAGE"'/' "$SYMFONY_APP_IMAGE_DOCKERFILE"
    else
        sed -i 's/^FROM .*/FROM '"$DOCKER_ACCOUNT"'\/'"$REGISTRY"'-'"$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE"'/' "$SYMFONY_APP_IMAGE_DOCKERFILE"
    fi

    sed -i 's/^FROM .*/FROM '"$DOCKER_ACCOUNT"'\/'"$REGISTRY"'-'"$SYMFONY_UBUNTU_BASE_PROD_IMAGE"'/' "$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_DOCKERFILE"
}

build_and_push_images() {
    for image in ${IMAGES[@]}; do
        local path_var="${image}_IMAGE_PATH"
        local name_var="${image}_IMAGE_NAME"
        local tag_var="${image}_IMAGE_TAG"
        local image_var="${image}_IMAGE"
        local dockerfile_var="${image}_IMAGE_DOCKERFILE"
        
        STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://hub.docker.com/v2/repositories/$DOCKER_ACCOUNT/$REGISTRY-${!name_var}/tags/${!tag_var}")

        if [ "$STATUS" != "200" ]; then
            log_info "Construyendo ${!image_var}..."
            docker build -t "$DOCKER_ACCOUNT/$REGISTRY-${!image_var}" -f "${!path_var}/${!dockerfile_var}" "${!path_var}" || { log_error "Error construyendo ${!image_var}"; exit 1; }
            docker push "$DOCKER_ACCOUNT/$REGISTRY-${!image_var}" || { log_error "Error subiendo ${!image_var}"; exit 1; }
        fi
    done
    
    log_info "✓ Imágenes construidas."
}

start_docker_containers() {
    log_info "Iniciando contenedores de Docker..."
    docker compose up -d #--build
    log_info "✓ Contenedores de Docker iniciados correctamente."
}

install_vendor_dependencies() {
    log_info "Instalando dependencias de vendor dentro del contenedor symfony-php-nginx-service..."
    # Instalar vendor en /var/www/html para que se refleje en el host
    docker compose exec symfony-php-nginx-service composer install --optimize-autoloader --working-dir=/var/www/html
    log_info "✓ Dependencias de vendor instaladas correctamente."
}

create_required_directories() {
    log_info "Creando directorios requeridos..."
    
    mkdir -p "$TEMPO_DATA_PATH"

    # Crear .gitignore
    cat > "$TEMPO_DATA_PATH""/.gitignore" <<EOF
# docker-compose/monitoring/tempo/tempo-data/.gitignore

# Ignorar todo
*
EOF

    mkdir -p "$VSCODE_SERVER_PATH"

    # Crear .gitignore
    cat > "$VSCODE_SERVER_PATH""/.gitignore" <<EOF
# php-nginx/symfony-app/.vscode-server/.gitignore

# Ignorar todo
*
EOF
    
    log_info "✓ Directorios creados correctamente."
}

change_permissions() {
    log_info "Cambiando permisos del directorio de la aplicación..."
    sudo chown -R $USER:$USER "$SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH"symfony-app && sudo chmod -R 777 "$SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH"symfony-app

    log_info "Cambiando permisos del directorio tempo-data..."
    sudo chown -R $USER:$USER "$TEMPO_DATA_PATH" && sudo chmod -R 777 "$TEMPO_DATA_PATH"

    chmod +x ./cleanup-docker-compose.sh

    log_info "✓ Permisos cambiados."
}

modify_dockerfiles

build_and_push_images

create_required_directories

change_permissions

start_docker_containers

install_vendor_dependencies

change_permissions
