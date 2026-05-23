#!/bin/bash

# Ejecuta chmod +x deploy-k8s.sh para hacer este script ejecutable.
# Luego ejecuta:
# ./deploy-k8s.sh deploy [local|aws] [DOCKER_ACCOUNT] → para empezar con el despliegue en local o AWS.
# ./deploy-k8s.sh test-blue-green [local|aws] [DOCKER_ACCOUNT] → para realizar la prueba de blue/green deployment y rollback automático.
# ./deploy-k8s.sh cleanup [local|aws] [DOCKER_ACCOUNT] → para limpiar el despliegue en local o AWS.

set -e

source common-k8s.sh
source cleanup-k8s.sh
source blue-green-rollback.sh

# ==================== CONFIGURACIÓN ====================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENVIRONMENT="" # Lo establece el usuario al ejecutar el script
DEPLOYED_LOCAL_RESOURCES_FILE="${SCRIPT_DIR}/.deployed-local-resources"
DEPLOYED_AWS_RESOURCES_FILE="${SCRIPT_DIR}/.deployed-aws-resources"

# ==================== COMPROBAR DEPENDENCIAS ====================
check_dependencies() {
    local env=$1
    local missing_deps=()
    
    log_info "Validando dependencias para entorno: $env"
    
    # Dependencias comunes para ambos entornos
    local common_deps=("docker" "kubectl" "python3")
    
    # Dependencias por entorno
    local local_deps=("minikube")
    local aws_deps=("aws" "terraform")
    
    # Comprobar dependencias comunes
    for cmd in "${common_deps[@]}"; do
        if ! command -v "$cmd" &> /dev/null; then
            missing_deps+=("$cmd")
        fi
    done
    
    # Comprobar dependencias por entorno
    if [ "$env" == "local" ]; then
        for cmd in "${local_deps[@]}"; do
            if ! command -v "$cmd" &> /dev/null; then
                missing_deps+=("$cmd")
            fi
        done
    else
        for cmd in "${aws_deps[@]}"; do
            if ! command -v "$cmd" &> /dev/null; then
                missing_deps+=("$cmd")
            fi
        done
        
        # Comprobar las credenciales de aws-cli
        if ! aws sts get-caller-identity &>/dev/null; then
            log_error "AWS CLI no se ha configurado con credenciales válidas. Ejecuta 'aws configure' primero."
            exit 1
        fi
        log_info "✓ Credenciales AWS válidos."
    fi
    
    if [ ${#missing_deps[@]} -gt 0 ]; then
        log_error "Dependencias faltantes: ${missing_deps[*]}"
        exit 1
    fi
}

# ==================== CONFIGURACIÓN DE DIRECTORIOS ====================
create_required_directories() {
    log_info "Creando directorios requeridos..."
    
    mkdir -p "$SCRIPT_DIR/""$TEMPO_DATA_PATH"

    # Crear .gitignore
    cat > "$SCRIPT_DIR/""$TEMPO_DATA_PATH""/.gitignore" <<EOF
# docker-compose/monitoring/tempo/tempo-data/.gitignore

# Ignorar todo
*
EOF

    mkdir -p "$SCRIPT_DIR/""$VSCODE_SERVER_PATH"

    # Crear .gitignore
    cat > "$SCRIPT_DIR/""$VSCODE_SERVER_PATH""/.gitignore" <<EOF
# php-nginx/symfony-app/.vscode-server/.gitignore

# Ignorar todo
*
EOF
    
    log_info "✓ Directorios creados correctamente."
}

# ==================== GENERACIÓN DE SECRETOS ====================
generate_local_secrets() {
    log_info "Generando secrets para Kubernetes (local)..."
    
    local secrets_dir="$KUSTOMIZATION_LOCAL_PATH/application/secrets"
    
    # Comprobar si los secretos existen
    if [ -f "$secrets_dir/secret-app-symfony.yaml" ] && [ -f "$secrets_dir/secret-database-symfony.yaml" ] && [ -f "$secrets_dir/secret-mysql.yaml" ] && [ -f "$secrets_dir/secret-user-queries.yaml" ]; then
        log_info "Los secrets ya existen en local."
        return
    fi

    mkdir -p "$secrets_dir"
    
    # Generar symfony-app-secret
    local app_secret="676bad43ce6494db4bc99a5be97212d2" # Valor por defecto
    local app_secret_b64=$(echo -n "$app_secret" | base64)
    
    # Generar mysql-secret
    local db_pass="XjesGl0qzB7CZNuW" # Valor por defecto
    local db_pass_b64=$(echo -n "$db_pass" | base64)
    
    # Crear secret-app-symfony.yaml
    cat > "$secrets_dir/secret-app-symfony.yaml" <<EOF
apiVersion: v1
kind: Secret
metadata:
  name: symfony-app-secret
  namespace: symfony-ns
type: Opaque
data:
  # Usar: echo -n "676bad43ce6494db4bc99a5be97212d2" | base64
  APP_SECRET: $app_secret_b64
EOF
    
    # Crear secret-database-symfony.yaml
    cat > "$secrets_dir/secret-database-symfony.yaml" <<EOF
apiVersion: v1
kind: Secret
metadata:
  name: symfony-database-secret
  namespace: symfony-ns
type: Opaque
data:
  # Usar: echo -n "mysql://root:XjesGl0qzB7CZNuW@mysql-service:3306/app?serverVersion=8.0&charset=utf8mb4" | base64
  DATABASE_URL: bXlzcWw6Ly9yb290OlhqZXNHbDBxekI3Q1pOdVdAbXlzcWwtc2VydmljZTozMzA2L2FwcD9zZXJ2ZXJWZXJzaW9uPTguMCZjaGFyc2V0PXV0ZjhtYjQ=
EOF

    # Crear secret-mysql.yaml
    cat > "$secrets_dir/secret-mysql.yaml" <<EOF
apiVersion: v1
kind: Secret
metadata:
  name: mysql-secret
  namespace: symfony-ns
type: Opaque
data:
  # Usar: echo -n "XjesGl0qzB7CZNuW" | base64
  MYSQL_ROOT_PASSWORD: $db_pass_b64 # "XjesGl0qzB7CZNuW" codificado en base64
EOF

    # Crear secret-user-queries.yaml
    cat > "$secrets_dir/secret-user-queries.yaml" <<EOF
apiVersion: v1
kind: Secret
metadata:
  name: user-queries-secret
  namespace: symfony-ns
type: Opaque
data:
  # Crear archivo con el contenido de las inserts y guardar
  # Aunque docker-entrypoint.sh sólo se ejecuta si detecta que /var/lib/mysql está vacío, preparamos los insert sin DELETE previo y con INSERT IGNORE para evitar errores de clave primaria duplicada
  # Usar: cat users.sql | base64 -w 0 # Es necesario eliminar saltos de línea, se hace con -w 0
  # Si lo guardas en un archivo, puedes ver el contenido mediante: base64 -d inserts-encrypted.sql
  # Puedes generar automáticamente este archivo mediante:
  # kubectl create secret generic user-queries-secret \
  # --from-file=USER_QUERIES=users.sql \
  # --dry-run=client -o yaml > secret-user-queries.yaml
  USER_QUERIES: LS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KLS0gSG9zdDogICAgICAgICAgICAgICAgICAgICAgICAgMTkyLjE2OC4xMDAuMgotLSBTZXJ2ZXIgdmVyc2lvbjogICAgICAgICAgICAgICA4LjAuNDUgLSBNeVNRTCBDb21tdW5pdHkgU2VydmVyIC0gR1BMCi0tIFNlcnZlciBPUzogICAgICAgICAgICAgICAgICAgIExpbnV4Ci0tIEhlaWRpU1FMIFZlcnNpb246ICAgICAgICAgICAgIDEyLjE2LjAuNzIyOQotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQoKLyohNDAxMDEgU0VUIEBPTERfQ0hBUkFDVEVSX1NFVF9DTElFTlQ9QEBDSEFSQUNURVJfU0VUX0NMSUVOVCAqLzsKLyohNDAxMDEgU0VUIE5BTUVTIHV0ZjggKi87Ci8qITUwNTAzIFNFVCBOQU1FUyB1dGY4bWI0ICovOwovKiE0MDEwMyBTRVQgQE9MRF9USU1FX1pPTkU9QEBUSU1FX1pPTkUgKi87Ci8qITQwMTAzIFNFVCBUSU1FX1pPTkU9JyswMDowMCcgKi87Ci8qITQwMDE0IFNFVCBAT0xEX0ZPUkVJR05fS0VZX0NIRUNLUz1AQEZPUkVJR05fS0VZX0NIRUNLUywgRk9SRUlHTl9LRVlfQ0hFQ0tTPTAgKi87Ci8qITQwMTAxIFNFVCBAT0xEX1NRTF9NT0RFPUBAU1FMX01PREUsIFNRTF9NT0RFPSdOT19BVVRPX1ZBTFVFX09OX1pFUk8nICovOwovKiE0MDExMSBTRVQgQE9MRF9TUUxfTk9URVM9QEBTUUxfTk9URVMsIFNRTF9OT1RFUz0wICovOwoKLS0gRHVtcGluZyBkYXRhIGZvciB0YWJsZSBhcHAudXNlcjogfjExIHJvd3MgKGFwcHJveGltYXRlbHkpCi0tIFVuYSB2ZXogY3JlYWRhIGxhIHRhYmxhIGVzdMOhIHZhY8OtYSwgbm8gZXMgbmVjZXNhcmlvIGVsaW1pbmFyIGRhdG9zCi0tIERFTEVURSBGUk9NIGB1c2VyYDsKLS0gRXN0ZSBzY3JpcHQgc2UgdGllbmUgcXVlIGVqZWN1dGFyIHPDs2xvIGVuIGVsIHByaW1lciBhcnJhbnF1ZSBkZSBsYSBCQkRELCBwZXJvIHBvciBzaSBsbGVnYXJhIGEgZWplY3V0YXJzZSBzdWNlc2l2YXMgdmVjZXMgYWwgaW5pY2lhbGl6YXIgZWwgcG9kLCBzZSBhw7FhZGUgSUdOT1JFIHBhcmEgZXZpdGFyIGVycm9yZXMgZGUgY2xhdmUgcHJpbWFyaWEgZHVwbGljYWRhCklOU0VSVCBJR05PUkUgSU5UTyBgdXNlcmAgKGBpZGAsIGBlbWFpbGAsIGByb2xlc2AsIGBwYXNzd29yZGAsIGBmaXJzdF9uYW1lYCkgVkFMVUVTCiAgKDEsICdhYnJhY2FfYWRtaW5AZXhhbXBsZS5jb20nLCAnW10nLCAnJDJ5JDEzJGxaZEE5enNWZjJ0REFCakJRcnVRWmUwcGJ5eGJMSThPb3J2UVVqVTI1ZERCeUZJdmxHbmhhJywgJ0tlYWdhbicpLAogICgyLCAnc3V6YW5uZTc4QGphc3QuY29tJywgJ1tdJywgJyQyeSQxMyQ1T3djZVE0Q3RyQWlheG5UOTNpUlh1bE9JL3lZTGN3Ukc5bXRhVmFnR1ZmRnpQejlmald5bScsICdKYXJyZXR0JyksCiAgKDMsICd5d2lzb3prQHlhaG9vLmNvbScsICdbXScsICckMnkkMTMkTEhEV3paZEVPSzNRaFBrcVBMS0hNT1BsT24wLjlOTDJqeHpHMG44RERtOFlOQ1lzcDVYM0MnLCAnU2hhaW5hJyksCiAgKDQsICdkcmVtcGVsQGJvZ2lzaWNoLmJpeicsICdbXScsICckMnkkMTMkYWN1Y1RCVmtNbEdGUW1Ed2xXcUhZLmJqSXFCVmV4TXdHSGhWV2dZZ0s4SUdEb3U4c0lzaDInLCAnQWRvbGZvJyksCiAgKDUsICdqYXJlZDg0QGdtYWlsLmNvbScsICdbXScsICckMnkkMTMkNVhWYVFGRDlKcnplaTBxa1hGVGMuZVc0aVRPOWdnZXNicU9jVy44Z3UzNnFCSXJZUm5KaS4nLCAnS3lsaWUnKSwKICAoNiwgJ3BqYWNvYnNAbWNkZXJtb3R0LmNvbScsICdbXScsICckMnkkMTMkcFdBbjVpcUF2RHRhRVg3L1N6Z0J4LkVGa1BmZUJKQlNCcG5GOHJPZS8zOTh3S3pqNTFNT0cnLCAnVG9ycmFuY2UnKSwKICAoNywgJ2FsZXhhbm5lLmtsZWluQGhhcnJpcy5jb20nLCAnW10nLCAnJDJ5JDEzJEJabmpCbzFrN3VMODZZQWZBLjZVZHVtQndEanJSbnNzRjQzSHVMSlM2em93SGxVZDVKSUlHJywgJ1NoZWxkb24nKSwKICAoOCwgJ2FsdGVud2VydGguYW5hYmVsQGdtYWlsLmNvbScsICdbXScsICckMnkkMTMkcW01QnEwQ1JjR1VBTGNBOTdEWFpkLjNrUmNxR0RTR2xUdEpucklVMVlIVFk1enpRY09aSE8nLCAnRGFyYnknKSwKICAoOSwgJ21jZGVybW90dC5ydXNzZWxAZmlzaGVyLmNvbScsICdbXScsICckMnkkMTMkREFlTGFjWjdvWkRNSC9yRUFUcUFSLnRMN3A3ckZUUUtxNGxKbjJhWkllbnhyUUlnUnR0Yy4nLCAnQ2hhcmxlcycpLAogICgxMCwgJ2Nhc3Blci5zYW50b3NAaG90bWFpbC5jb20nLCAnW10nLCAnJDJ5JDEzJGR1WmJYbnJhMlkwazJsYmNTVVYyOC5MUmdwdngybENXNEJleFNxa3RXNVR2M2Zoeno0QVg2JywgJ1dpbm5pZnJlZCcpLAogICgxMSwgJ2Jvcm5AaG90bWFpbC5jb20nLCAnW10nLCAnJDJ5JDEzJDFWd3czQmNhTGlDZEtsc0J4TGxsbXV5bGF4VVBhQzZmL0hwbW5xUGVuL3guWTF0aFhLTklHJywgJ0FsbHknKTsKCi8qITQwMTAzIFNFVCBUSU1FX1pPTkU9SUZOVUxMKEBPTERfVElNRV9aT05FLCAnc3lzdGVtJykgKi87Ci8qITQwMTAxIFNFVCBTUUxfTU9ERT1JRk5VTEwoQE9MRF9TUUxfTU9ERSwgJycpICovOwovKiE0MDAxNCBTRVQgRk9SRUlHTl9LRVlfQ0hFQ0tTPUlGTlVMTChAT0xEX0ZPUkVJR05fS0VZX0NIRUNLUywgMSkgKi87Ci8qITQwMTAxIFNFVCBDSEFSQUNURVJfU0VUX0NMSUVOVD1AT0xEX0NIQVJBQ1RFUl9TRVRfQ0xJRU5UICovOwovKiE0MDExMSBTRVQgU1FMX05PVEVTPUlGTlVMTChAT0xEX1NRTF9OT1RFUywgMSkgKi87Cg==
EOF

    # Crear .gitignore
    cat > "$secrets_dir/.gitignore" <<EOF
# k8s/overlays/local/application/secrets/.gitignore

# Ignorar todo
*
EOF
    
    log_info "✓ Secrets generados en $secrets_dir"
}

generate_aws_secrets() {
    log_info "Generando archivo terraform.tfvars para AWS..."
    
    local tfvars_file="$SCRIPT_DIR/../infra/main/terraform.tfvars"
    
    # Comprobar si tfvars ya existe
    if [ -f "$tfvars_file" ]; then
        log_warn "terraform.tfvars ya existe. Usando valores existentes."
        return
    fi
    
    # Generar el contenido de tfvars
    cat > "$tfvars_file" <<'REALEND'
aws_region = "eu-west-1"
aws_ebs_az = "eu-west-1a"
project_name = "pf-devops"
environment = "test"
vpc_cidr = "10.0.0.0/16"
subnet_a_cidr = "10.0.1.0/24"
subnet_b_cidr = "10.0.2.0/24"
subnet_c_cidr = "10.0.3.0/24"
subnet_d_cidr = "10.0.4.0/24"

# Secrets
symfony_app_secret = "676bad43ce6494db4bc99a5be97212d2"
symfony_database_url = "mysql://root:XjesGl0qzB7CZNuW@mysql-service:3306/app?serverVersion=8.0&charset=utf8mb4"
mysql_root_password = "XjesGl0qzB7CZNuW"
user_queries = <<EOF
-- --------------------------------------------------------
-- Host:                         192.168.100.2
-- Server version:               8.0.45 - MySQL Community Server - GPL
-- Server OS:                    Linux
-- HeidiSQL Version:             12.16.0.7229
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping data for table app.user: ~11 rows (approximately)
-- Una vez creada la tabla está vacía, no es necesario eliminar datos
-- DELETE FROM `user`;
-- Este script se tiene que ejecutar sólo en el primer arranque de la BBDD, pero por si llegara a ejecutarse sucesivas veces al inicializar el pod, se añade IGNORE para evitar errores de clave primaria duplicada
INSERT IGNORE INTO `user` (`id`, `email`, `roles`, `password`, `first_name`) VALUES
  (1, 'abraca_admin@example.com', '[]', '$2y$13$lZdA9zsVf2tDABjBQruQZe0pbyxbLI8OorvQUjU25dDByFIvlGnha', 'Keagan'),
  (2, 'suzanne78@jast.com', '[]', '$2y$13$5OwceQ4CtrAiaxnT93iRXulOI/yYLcwRG9mtaVagGVfFzPz9fjWym', 'Jarrett'),
  (3, 'ywisozk@yahoo.com', '[]', '$2y$13$LHDWzZdEOK3QhPkqPLKHMOPlOn0.9NL2jxzG0n8DDm8YNCYsp5X3C', 'Shaina'),
  (4, 'drempel@bogisich.biz', '[]', '$2y$13$acucTBVkMlGFQmDwlWqHY.bjIqBVexMwGHhVWgYgK8IGDou8sIsh2', 'Adolfo'),
  (5, 'jared84@gmail.com', '[]', '$2y$13$5XVaQFD9Jrzei0qkXFTc.eW4iTO9ggesbqOcW.8gu36qBIrYRnJi.', 'Kylie'),
  (6, 'pjacobs@mcdermott.com', '[]', '$2y$13$pWAn5iqAvDtaEX7/SzgBx.EFkPfeBJBSBpnF8rOe/398wKzj51MOG', 'Torrance'),
  (7, 'alexanne.klein@harris.com', '[]', '$2y$13$BZnjBo1k7uL86YAfA.6UdumBwDjrRnssF43HuLJS6zowHlUd5JIIG', 'Sheldon'),
  (8, 'altenwerth.anabel@gmail.com', '[]', '$2y$13$qm5Bq0CRcGUALcA97DXZd.3kRcqGDSGlTtJnrIU1YHTY5zzQcOZHO', 'Darby'),
  (9, 'mcdermott.russel@fisher.com', '[]', '$2y$13$DAeLacZ7oZDMH/rEATqAR.tL7p7rFTQKq4lJn2aZIenxrQIgRttc.', 'Charles'),
  (10, 'casper.santos@hotmail.com', '[]', '$2y$13$duZbXnra2Y0k2lbcSUV28.LRgpvx2lCW4BexSqktW5Tv3fhzz4AX6', 'Winnifred'),
  (11, 'born@hotmail.com', '[]', '$2y$13$1Vww3BcaLiCdKlsBxLllmuylaxUPaC6f/HpmnqPen/x.Y1thXKNIG', 'Ally');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

EOF
REALEND
    
    log_info "✓ terraform.tfvars generado. Edítalo con tus valores reales si es necesario."
}

# ==================== DESPLIEGUE LOCAL ====================
deploy_local() {
    echo "================================"
    echo "INICIANDO DESPLIEGUE LOCAL"
    echo "================================"

    # Guardar información del despliegue
    echo "local" > "$DEPLOYED_LOCAL_RESOURCES_FILE"
    
    check_dependencies "local"
    create_required_directories
    change_permissions
    generate_local_secrets
    
    # Iniciar Docker y Minikube
    start_docker_service
    start_minikube
    enable_addons
    
    # Modificar Dockerfiles y deployment
    modify_dockerfiles
    
    # Construir y desplegar
    build_and_push_images
    wait_for_ingress_controller
    log_info "Desplegando aplicación con Kustomize..."
    apply_k8s_resources "$KUSTOMIZATION_LOCAL_PATH"
    verify_services
    add_ingress_to_hosts
    
    log_info "================================"
    log_info "✓ DESPLIEGUE LOCAL COMPLETADO"
    log_info "================================"
    log_info "Accede a la aplicación en:"
    log_info "  http://symfony.local"
    log_info "Accede a la monitorización en:"
    log_info "  http://symfony.local/grafana"
    log_info "Para probar blue/green deployment, ejecuta:"
    log_info "  ./deploy-k8s.sh test-blue-green local"
    log_info "Para limpiar, ejecuta:"
    log_info "  ./deploy-k8s.sh cleanup local"
}

# ==================== TERRAFORM INFRA ====================
create_terraform_bootstrap_infra() {
    # Bootstrap infra    
    cd "$SCRIPT_DIR/../infra/bootstrap"
    terraform init
    terraform apply -auto-approve
}

create_terraform_main_infra() {
    # Obtener output del bucket
    local bucket_name=$(terraform output -raw bucket_name)
    cd - > /dev/null
    
    # Main infra
    cd "$SCRIPT_DIR/../infra/main"
    terraform init \
        -backend-config="bucket=$bucket_name" \
        -backend-config="key=main/terraform.tfstate" \
        -backend-config="region=eu-west-1" \
        -backend-config="dynamodb_table=terraform-lock" \
        -backend-config="encrypt=true"
    terraform apply -auto-approve
}

# ==================== CONFIGURAR KUBECTL PARA AWS ====================
setup_kubectl_for_aws() {
    # Obtener outputs
    local cluster_name=$(terraform output -raw cluster_name)
    local aws_region=$(terraform output -raw region)
    cd - > /dev/null
    
    # Configurar kubectl para AWS
    aws eks --region "$aws_region" update-kubeconfig --name "$cluster_name"

    local context=$(kubectl config get-contexts -o name | grep 'arn:aws:eks:eu-west-1:' | grep ':cluster/pf-devops-eks-')

    # Guardar información de despliegue
    echo "cluster_name=$cluster_name" >> "$DEPLOYED_AWS_RESOURCES_FILE"
    echo "aws_region=$aws_region" >> "$DEPLOYED_AWS_RESOURCES_FILE"
    echo "context=$context" >> $DEPLOYED_AWS_RESOURCES_FILE
    
    # Verificar acceso al clúster
    if ! kubectl cluster-info &>/dev/null; then
        log_error "No se puede acceder al clúster EKS. Verifica tus credenciales de AWS."
        exit 1
    fi
    log_info "✓ Acceso a EKS configurado."
}

# ==================== DESPLIEGUE AWS ====================
deploy_aws() {
    log_info "================================"
    log_info "INICIANDO DESPLIEGUE AWS EKS"
    log_info "================================"

    # Guardar información de despliegue
    echo "aws" > "$DEPLOYED_AWS_RESOURCES_FILE"
    
    check_dependencies "aws"
    create_required_directories
    change_permissions
    generate_aws_secrets
    
    log_warn "IMPORTANTE: El despliegue de EKS puede tardar alrededor de 10 minutos."
    log_info "Se mostrará el progreso de Terraform en tiempo real."
    
    log_info "Paso 1: Creando infraestructura base (S3 bucket)..."
    create_terraform_bootstrap_infra

    log_info "Paso 2: Creando infraestructura principal: VPC, EKS, EFS, EBS..."
    log_warn "Este paso puede tardar alrededor de 10 minutos..."
    create_terraform_main_infra    

    log_info "Paso 3: Configurando kubectl para acceder al clúster EKS..."
    setup_kubectl_for_aws
    
    log_info "Paso 4: Construyendo imágenes Docker..."
    build_and_push_images
    
    log_info "Paso 5: Desplegando aplicación con Kustomize..."
    apply_k8s_resources "$KUSTOMIZATION_AWS_PATH"
    
    log_info "Paso 6: Verificando despliegue..."
    verify_services
    
    log_info "================================"
    log_info "✓ DESPLIEGUE AWS COMPLETADO"
    log_info "================================"
    
    local host_url=$(kubectl get ingress -n "$SYMFONY_NAMESPACE_NAME" -o jsonpath='{.items[0].spec.rules[0].host}')    
    log_info "Accede a la aplicación en:"
    log_info "  http://$host_url"
    log_info "Accede a la monitorización en:"
    log_info "  http://$host_url/grafana"
    log_info "Para probar blue/green deployment, ejecuta:"
    log_info "  ./deploy-k8s.sh test-blue-green aws"
    log_info "Para limpiar, ejecuta:"
    log_info "  ./deploy-k8s.sh cleanup aws"
}

wait_for_ingress_controller() {
    local max_wait=120
    local elapsed=0
    local namespace="ingress-nginx"
    local selector="app.kubernetes.io/name=ingress-nginx"
    
    log_info "Esperando Ingress Controller..."
    
    while [ $elapsed -lt $max_wait ]; do
        # EndpointSlices (K8s 1.21+)
        local ready_endpoints=$(kubectl get endpointslices -n $namespace \
            -l kubernetes.io/service-name=ingress-nginx-controller-admission \
            -o jsonpath='{.items[*].endpoints[*].conditions.ready}' 2>/dev/null | grep -c true)
        
        # Pods ready (fallback)
        local ready_pods=$(kubectl get pods -n $namespace -l $selector \
            -o jsonpath='{.items[*].status.conditions[?(@.type=="Ready")].status}' 2>/dev/null | grep -c True)
        
        if [ "$ready_endpoints" -gt 0 ] || [ "$ready_pods" -gt 0 ]; then
            echo ""
            log_info "✓ Ingress Controller listo (endpoints: $ready_endpoints, pods: $ready_pods)"
            sleep 5
            return 0
        fi
        
        echo -n "."
        sleep 2
        elapsed=$((elapsed + 2))
    done
    
    log_error "Ingress Controller no disponible después de ${max_wait}s"
    return 1
}

apply_k8s_resources() {
    local kustomization_path=$1
    
    kubectl apply -k "$kustomization_path"
}

add_ingress_to_hosts() {
    if grep -q "$INGRESS_HOST" /etc/hosts; then
        log_info "El host '$INGRESS_HOST' ya está configurado en /etc/hosts"
    else
        MINIKUBE_IP=$(minikube ip)
        log_info "Añadiendo $INGRESS_HOST -> $MINIKUBE_IP a /etc/hosts"
        echo "$MINIKUBE_IP    $INGRESS_HOST" | sudo tee -a /etc/hosts > /dev/null
        log_info "✓ Host añadido correctamente."
    fi
}

change_permissions() {
    sudo chown -R $USER:$USER "$SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH"symfony-app && sudo chmod -R 777 "$SYMFONY_UBUNTU_BASE_PROD_IMAGE_PATH"symfony-app

    sudo chown -R $USER:$USER "$TEMPO_DATA_PATH" && sudo chmod -R 777 "$TEMPO_DATA_PATH"
}

modify_dockerfiles() {
    if [ "$APP_IMAGE_TYPE" == "$PROD_TYPE" ]; then
        sed -i 's/^FROM .*/FROM '"$DOCKER_ACCOUNT"'\/'"$REGISTRY"'-'"$SYMFONY_UBUNTU_BASE_PROD_IMAGE"'/' "$SYMFONY_APP_IMAGE_DOCKERFILE"
    else
        sed -i 's/^FROM .*/FROM '"$DOCKER_ACCOUNT"'\/'"$REGISTRY"'-'"$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE"'/' "$SYMFONY_APP_IMAGE_DOCKERFILE"
    fi

    sed -i 's/^FROM .*/FROM '"$DOCKER_ACCOUNT"'\/'"$REGISTRY"'-'"$SYMFONY_UBUNTU_BASE_PROD_IMAGE"'/' "$SYMFONY_UBUNTU_BASE_DEBUG_IMAGE_DOCKERFILE"

    sed -i 's|image: .*|image: '"$DOCKER_ACCOUNT"'/'"$REGISTRY-""$SYMFONY_APP_IMAGE"'|' "$DEPLOYMENT_SYMFONY_LOCAL_PATH"
    sed -i 's|image: .*|image: '"$DOCKER_ACCOUNT"'/'"$REGISTRY-""$SYMFONY_APP_IMAGE"'|' "$DEPLOYMENT_SYMFONY_AWS_PATH"
}

# ==================== CONSTRUIR IMAGEN ====================
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
    
    log_info "✓ Imágenes construidas"
}

# ==================== VERIFICAR PODS ====================
verify_services() {
    log_info "Verificando despliegue..."
    
    for namespace_name in "${NAMESPACES_NAMES[@]}"; do    
        log_info "Esperando pods en $namespace_name..."
        
        if kubectl wait --for=condition=ready pod --all -n "$namespace_name" --timeout=300s 2>/dev/null; then
            log_info "✓ Pods listos en $namespace_name"
            kubectl get pods -n "$namespace_name"
        else
            log_warn "Timeout: Los pods no están listos en $namespace_name"
            kubectl get pods -n "$namespace_name"
            return 1
        fi
    done
}

# ==================== MENÚ PRINCIPAL ====================
show_menu() {
    echo ""
    echo "================================"
    echo "SCRIPT DE DESPLIEGUE"
    echo "================================"
    echo "Selecciona el entorno de despliegue:"
    echo "  1) Local (Minikube)."
    echo "  2) AWS (EKS)."
    echo "  3) Salir."
    echo ""
    read -p "Opción (1-3): " option
    
    case $option in
        1)
            ENVIRONMENT="local"
            deploy_local
            ;;
        2)
            ENVIRONMENT="aws"
            deploy_aws
            ;;
        3)
            log_info "Saliendo..."
            exit 0
            ;;
        *)
            log_error "Opción inválida."
            show_menu
            ;;
    esac
    
    echo ""
    while true; do
        read -p "¿Deseas probar blue/green deployment? (s/n): " -n 1 -r
        echo ""

        if [[ $REPLY =~ ^[Ss]$ ]]; then
            test_blue_green_deployment "$ENVIRONMENT"
            break
        elif [[ $REPLY =~ ^[Nn]$ ]]; then
            log_info "Para probar blue/green deployment, ejecuta:"
            log_info "  ./deploy-k8s.sh test-blue-green $ENVIRONMENT"
            break
        else
            echo "Por favor, responde 's' o 'n'."
        fi
    done
    
    echo ""
    while true; do
        read -p "¿Deseas limpiar todos los recursos? (s/n): " -n 1 -r
        echo ""

        if [[ $REPLY =~ ^[Ss]$ ]]; then
            cleanup_deployment "$ENVIRONMENT"
            break
        elif [[ $REPLY =~ ^[Nn]$ ]]; then
            log_info "Para limpiar, ejecuta:"
            log_info "  ./deploy-k8s.sh cleanup $ENVIRONMENT"
            break
        else
            echo "Por favor, responde 's' o 'n'."
        fi
    done
}

# ==================== SCRIPT ENTRY POINT ====================
if [ $# -eq 0 ]; then
    show_menu
else
    # Permitir sobrescribir DOCKER_ACCOUNT como tercer argumento
    if [ $# -ge 3 ]; then
        export DOCKER_ACCOUNT="$3"
    fi
    case "$1" in
        deploy)
            ENVIRONMENT="${2:-local}"
            if [ "$ENVIRONMENT" == "local" ]; then
                deploy_local
            elif [ "$ENVIRONMENT" == "aws" ]; then
                deploy_aws
            else
                log_error "Entorno no válido: $ENVIRONMENT. Usa 'local' o 'aws'"
            fi
            ;;
        test-blue-green)
            ENVIRONMENT="${2:-local}"
            if [ "$ENVIRONMENT" == "local" ]; then
                if [ ! -f "$DEPLOYED_LOCAL_RESOURCES_FILE" ]; then
                    log_error "No hay despliegue anterior."
                fi
                test_blue_green_deployment "$ENVIRONMENT"
            elif [ "$ENVIRONMENT" == "aws" ]; then
                if [ ! -f "$DEPLOYED_AWS_RESOURCES_FILE" ]; then
                    log_error "No hay despliegue anterior."
                fi
                test_blue_green_deployment "$ENVIRONMENT"
            else
                log_error "Entorno no válido: $ENVIRONMENT. Usa 'local' o 'aws'"
            fi
            ;;
        cleanup)
            ENVIRONMENT="${2:-local}"
            if [ "$ENVIRONMENT" == "local" ]; then
                if [ ! -f "$DEPLOYED_LOCAL_RESOURCES_FILE" ]; then
                    log_error "No hay despliegue para limpiar."
                fi
                cleanup_deployment "$ENVIRONMENT"
            elif [ "$ENVIRONMENT" == "aws" ]; then
                if [ ! -f "$DEPLOYED_AWS_RESOURCES_FILE" ]; then
                    log_error "No hay despliegue para limpiar."
                fi
                cleanup_deployment "$ENVIRONMENT"
            else
                log_error "Entorno no válido: $ENVIRONMENT. Usa 'local' o 'aws'"
            fi
            ;;
        *)
            log_error "Uso: $0 [deploy|test-blue-green|cleanup] [local|aws] [DOCKER_ACCOUNT]"
            ;;
    esac
fi
