# Proyecto DevOps: Symfony + Kubernetes + AWS

## ✨ Implementación de un ecosistema DevOps. Proyecto final.

1. **Contenedorización de la aplicación** con Docker/Dockerfile/Docker Compose.

2. **Orquestación con Kubernetes**:
    - Infraestructura como código (IaC) con Terraform.
    - Despliegue de la aplicación en Kubernetes.

3. **Integración y entrega continua (CI/CD)**:
    - Pipeline de CI/CD en GitHub Actions:
        - Compilación y pruebas de la aplicación.
        - Construcción de la imagen Docker y envío a registro de contenedores (Docker Hub o GitHub Container Registry).
        - Despliegue en el clúster de Kubernetes.
    - Manejo seguro de credenciales y secretos durante todo el proceso.

4. **Despliegues con Blue-Green deployment y rollback automático**:
    - Implementación de estrategia Blue-Green para minimizar el tiempo de inactividad.
    - Configuración de mecanismos de rollback automático en caso de fallos.

5. **Monitorización y observabilidad**:
    - Sistema de monitorización que incluye:
        - OpenTelemetry Collector para recolección y exportación de métricas y trazas.
        - Prometheus como servidor de métricas que recoge datos de los exporters.
        - Grafana para visualización de métricas y dashboards personalizados.
        - Loki para gestión centralizada de logs.
        - cAdvisor y Node Exporter para métricas del clúster.
        - Alertas y notificaciones basadas en métricas y logs críticos.

## 📋 Índice

1. [Requisitos Previos](#requisitos-previos)
2. [Despliegue Rápido](#despliegue-rapido)
3. [Despliegue Manual en AWS](#despliegue-manual)
4. [Blue/Green Deployment y rollback automático](#bluegreen-deployment-y-rollback-automatico)
5. [Destruir despliegue](#destruir-despliegue)
6. [Secretos y Configuración](#secretos-y-configuracion)
7. [Workflows de GitHub Actions](#workflows-de-github-actions)
8. [Arquitectura Desplegada](#arquitectura-desplegada)
9. [Troubleshooting](#troubleshooting)
10. [Documentación Adicional](#documentacion-adicional)
11. [Notas Importantes](#notas-importantes)
12. [Proyecto Educativo](#proyecto-educativo)
13. [Licencia](#licencia)

## 🔧 Requisitos Previos <a name="requisitos-previos" />

### Para ambos entornos (local y AWS)

- **Docker**: v29.4.0+
- **kubectl**: v1.35.4+ (con Kustomize v5.7.1+)
- **Python3**: v3.13.5+
- **Credenciales Docker Hub configuradas** (opcional)

### Sólo para despliegue LOCAL

- **Minikube**: v1.37.0+ (con Kubernetes v1.34.0, Docker 28.4.0)
- **RAM**: 4 GB mínimo
- **Sistema Operativo**: Debian 13 (probado)

### Sólo para despliegue AWS

- **aws-cli**: v2.23.6+
- **Terraform**: v1.14.8+
- **Credenciales AWS configuradas** con permisos de Administrador

```bash
# Verificar versiones instaladas
docker --version
kubectl version --client
minikube version     # Para local
aws --version        # Para AWS
terraform version    # Para AWS
```

### Configurar AWS CLI

```bash
aws configure
# Se te pedirá:
# AWS Access Key ID
# AWS Secret Access Key
# Default region: eu-west-1
# Default output: json

# Verificar configuración
aws sts get-caller-identity
```

### Configurar credenciales para Docker Hub (opcional)

```bash
# Asegúrate de iniciar el servicio de Docker
sudo systemctl start docker

# Crea un Personal Access Token (PAT) desde https://app.docker.com/settings

# Inicia sesión en docker-cli reemplazando tu nombre de usuario en dockerhub_username
# Luego se te pedirá tu PAT:
export $DOCKER_ACCOUNT=dockerhub_username
docker login -u "$DOCKER_ACCOUNT"

# Verificar configuración
docker info | grep "Username:"
```

## 🚀 Despliegue Rápido <a name="despliegue-rapido" />

El script `deploy-k8s.sh` automatiza todo el proceso. Ofrece un menú interactivo:

### Opción 1: Despliegue LOCAL (Minikube)

```bash
cd k8s
chmod +x deploy-k8s.sh
./deploy-k8s.sh

# Selecciona opción 1 en el menú
```

**Lo que hace automáticamente:**
- Valida todas las dependencias
- Inicia Docker y Minikube
- Crea directorios necesarios
- Genera secrets de Kubernetes
- Construye las imágenes Docker
- Despliega en Minikube
- Configura `/etc/hosts` para poder acceder con ingress

**Resultado:**
```
✓ Accede a:
http://symfony.local → Sitio web
http://symfony.local/grafana → Monitorización
```

### Opción 2: Despliegue AWS (EKS)

```bash
cd k8s
chmod +x deploy-k8s.sh
./deploy-k8s.sh

# Selecciona opción 2 en el menú
```

**Lo que hace automáticamente:**
- Valida todas las dependencias y AWS CLI
- Crea infraestructura base (S3 bucket para estado remoto de la infraestructura principal)
- Crea infraestructura principal (VPC, EKS, EC2) ~10 minutos
- Genera secrets y configura terraform.tfvars
- Construye y sube imágenes a Docker Hub si fuera necesario (las imágenes son públicas y accesibles)
- Despliega la aplicación en EKS

**Resultado:**
```bash
# Obtener URL del ALB (Application Load Balancer)
kubectl get ingress -n symfony-ns

# Ejemplos:
# http://symfony-alb-151921333.eu-west-1.elb.amazonaws.com
# http://symfony-alb-151921333.eu-west-1.elb.amazonaws.com/grafana

```

### Opciones del Script

El script también soporta argumentos:

```bash
# Desplegar programáticamente
cd <directorio-proyecto>/k8s

./deploy-k8s.sh deploy local [DOCKER_ACCOUNT]   # Desplegar en local
./deploy-k8s.sh deploy aws [DOCKER_ACCOUNT]     # Desplegar en AWS

# Probar blue/green y rollback automático
./deploy-k8s.sh test-blue-green local [DOCKER_ACCOUNT]
./deploy-k8s.sh test-blue-green aws [DOCKER_ACCOUNT]

# Limpiar recursos
./deploy-k8s.sh cleanup local [DOCKER_ACCOUNT]
./deploy-k8s.sh cleanup aws [DOCKER_ACCOUNT]
```

## 🔧 Despliegue Manual en AWS <a name="despliegue-manual" />

Si prefieres hacer los pasos manualmente:

### Paso 1: Crear infraestructura base

```bash
cd infra/bootstrap
terraform init
terraform apply
# Espera a que termine y guarda el nombre del bucket en una variable mediante:
BUCKET_NAME=$(terraform output -raw bucket_name)
```

### Paso 2: Crear infraestructura principal (AWS)

```bash
cd ../main
terraform init \
  -backend-config="bucket=$BUCKET_NAME" \
  -backend-config="key=main/terraform.tfstate" \
  -backend-config="region=eu-west-1" \
  -backend-config="dynamodb_table=terraform-lock" \
  -backend-config="encrypt=true"
terraform apply
# IMPORTANTE: Esto tardará ~10 minutos
```

### Paso 3: Configurar kubectl

```bash
# Para AWS EKS
# Sin salir del directorrio main, ejecutar:
aws eks --region $(terraform output -raw region) update-kubeconfig --name $(terraform output -raw cluster_name)

# Verificar conexión
kubectl cluster-info
```

### Paso 4: Construir y subir imágenes (opcional)

Realiza este paso sólo si hiciste "Configurar credenciales para Docker Hub (opcional)".

```bash
# Volver al directorio raíz del proyecto
cd ../..

docker build -t "$DOCKER_ACCOUNT/mysymfony-ubuntu:24.04-5.1-prod" -f php-nginx/Dockerfile.base.prod php-nginx/
docker push "$DOCKER_ACCOUNT/mysymfony-ubuntu:24.04-5.1-prod"

# Edita Dockerfile.app para que use el registro de tu cuenta de Docker Hub:
sed -i 's/^FROM .*/FROM '"$DOCKER_ACCOUNT"'\/mysymfony-ubuntu:24.04-5.1-prod/' php-nginx/Dockerfile.app

docker build -t "$DOCKER_ACCOUNT/mysymfony-php-nginx:7.1-prod" -f php-nginx/Dockerfile.app php-nginx/
docker push "$DOCKER_ACCOUNT/mysymfony-php-nginx:7.1-prod"
```

### Paso 5: Desplegar con Kustomize

```bash
# Si quieres usar las imágenes que construiste opcionalmente, actualiza los dos deployments. En caso contrario, omite este paso:
sed -i 's|image: .*|image: '"$DOCKER_ACCOUNT"'/mysymfony-php-nginx:7.1-prod|' k8s/overlays/local/application/deployments/deployment-symfony.yaml
sed -i 's|image: .*|image: '"$DOCKER_ACCOUNT"'/mysymfony-php-nginx:7.1-prod|' k8s/overlays/aws/application/deployments/deployment-symfony.yaml

# Despliega con Kustomize
cd <directorio-proyecto>/k8s

kubectl apply -k overlays/aws/
```

### Paso 6: Verificar despliegue

```bash
# Ver pods
kubectl get pods -n symfony-ns
kubectl get pods -n monitoring-ns

# Ver servicios
kubectl get services -n symfony-ns
kubectl get services -n monitoring-ns

# Ver ingress
kubectl get ingress -n symfony-ns
kubectl get ingress -n monitoring-ns

# Acceder a la aplicación
# AWS: http://<ALB-DNS>

# Acceder a la monitorización
# AWS: http://<ALB-DNS>/grafana

# Ver logs del pod en tiempo real
kubectl logs -n symfony-ns deployment/symfony-app -f

# Ejecutar debug-pod
kubectl run debug-pod --image=nicolaka/netshoot:latest -it --rm --restart=Never -- /bin/bash
curl http://<IP_SERVICIO>

# Verificar ALB Controller
kubectl get pods -n kube-system -l app.kubernetes.io/name=aws-load-balancer-controller
```

## 🔄 Blue/Green Deployment y rollback automático <a name="bluegreen-deployment-y-rollback-automatico" />

El script incluye soporte integrado para blue/green deployment y rollback automático:

```bash
# Durante el despliegue, el script ofrecerá probar blue/green:
# ¿Deseas probar blue/green deployment? (s/n):

# Responde 's' y el script:
# 1. Creará un nuevo deployment GREEN
# 2. Probará su salud (/health)
# 3. Te preguntará si cambiar el tráfico a GREEN
# 4. Probará /error-test para simular un error
# 5. Ejecutará rollback automático a BLUE
```

## 🗑️ Destruir despliegue <a name="destruir-despliegue" />

### Usando el script

```bash
cd <directorio-proyecto>/k8s

# Para local
./deploy-k8s.sh cleanup local

# Para AWS
./deploy-k8s.sh cleanup aws
```

### Eliminar despliegue manualmente

```bash
cd <directorio-proyecto>/k8s

# Para local
kubectl delete -k overlays/local/

# Para AWS
kubectl delete -k overlays/aws/
```

### Destruir infraestructura manualmente

```bash
cd ../infra/main
terraform destroy

cd ../bootstrap
terraform destroy
```

### Limpiar contextos de kubectl manualmente

```bash
kubectl config get-contexts && \
kubectl config use-context minikube

kubectl config delete-context <arn:aws:eks:eu-west-1:context-name> && \
kubectl config delete-cluster <arn:aws:eks:eu-west-1:context-name> && \
kubectl config delete-user    <arn:aws:eks:eu-west-1:context-name>
```

## 🔐 Secretos y Configuración <a name="secretos-y-configuracion" />

### Secretos de Kubernetes

Los secretos se generan automáticamente en `k8s/overlays/local/application/secrets/`:

- `secret-app-symfony.yaml`: APP_SECRET para Symfony
- `secret-database-symfony.yaml`: DATABASE_URL para conexión a MySQL
- `secret-mysql.yaml`: Credenciales de MySQL
- `secret-user-queries.yaml`: Queries de los usuarios iniciales de la BD

Estos archivos están en `.gitignore`. El script los regenera automáticamente si no existen.

### Variables de Terraform (AWS)

El archivo `infra/main/terraform.tfvars` contiene:

```hcl
aws_region = "eu-west-1"
project_name = "pf-devops"
environment = "test"
...

# Secrets
symfony_app_secret = "..."
symfony_database_url = "..."
mysql_root_password = "..."
user_queries = "..."
```

Este archivo está en `.gitignore` y se genera automáticamente.

## 🤖 Workflows de GitHub Actions <a name="workflows-de-github-actions" />

### Estructura

Los workflows están en `.github/workflows/`:

1. **build.yml**: Construcción y push a Docker Hub
2. **test.yml**: Tests unitarios, Trivy, Gitleaks
3. **deploy.yml**: Blue/green deployment en EKS

### Configuración de GitHub Secrets

Para que los workflows funcionen correctamente con Docker Hub, necesitas configurar estos secrets en GitHub:

**Ve a**: `Settings → Secrets and variables → Actions → New repository secret`

#### Secrets de Docker Hub (obligatorios para nuevas construcciones de imágenes):

```bash
# Tu usuario de Docker Hub
DOCKER_ACCOUNT=<tu-nombre-de-usuario>

# Token de acceso personal de Docker Hub
# Obtenerlo en: https://hub.docker.com/settings/security → New Access Token
DOCKER_TOKEN=<tu-docker-hub-personal-access-token>
```

#### Secrets de AWS (obligatorios para deploy en EKS):

```bash
# Si quieres desplegar en AWS EKS después de build
AWS_ACCESS_KEY_ID=<tu-access-key>
AWS_SECRET_ACCESS_KEY=<tu-secret-key>
AWS_REGION=eu-west-1
EKS_CLUSTER_NAME=pf-devops-eks
```

### Flujo de Workflows

El flujo completo es:

```
Push a main
    ↓
build.yml se ejecuta
  ├─ Login a Docker Hub
  ├─ Pull de imágenes base (si existen)
  ├─ Build y push de imágenes (si no existen)
  ├─ Scan con Trivy/GitLeaks
  └─ Trigger automático de test.yml
    ↓
test.yml se ejecuta (automático después de build)
  ├─ Unit tests
  └─ Dependency check
    ↓
deploy.yml se ejecuta (automático después de test)
  ├─ Conecta a AWS EKS
  ├─ Crea deployment GREEN (v2)
  ├─ Health checks
  ├─ Cambio de tráfico
  ├─ Monitoreo 30s
  └─ Auto-rollback si error
```

## 📊 Arquitectura Desplegada <a name="arquitectura-desplegada" />

### Local (Minikube)

```
┌────────────────────────────────────────┐
│      Minikube Kubernetes Cluster       │
├────────────────────────────────────────┤
│  Namespace: symfony-ns                 │
│  ├─ Deployment: symfony-app (v1)       │
│  │  └─ Pod: php-nginx (2 réplicas)     │
│  ├─ Deployment: mysql                  │
│  ├─ Service: nginx-service (ClusterIP) │
│  ├─ Service: mysql-service (ClusterIP) │
│  └─ Ingress: symfony-ingress           │
│                                        │
│  Namespace: monitoring-ns              │
│  ├─ Prometheus, Grafana                │
│  ├─ Loki, Tempo                        │
│  ├─ NodeExporter, cAdvisor             │
│  └─ OTEL Collector                     │
└────────────────────────────────────────┘
         ↓
   Ingress Controller (nginx w/ sticky sessions)
         ↓
   http://symfony.local
```

### AWS (EKS)

```
┌────────────────────────────────────────┐
│       AWS EKS Kubernetes Cluster       │
├────────────────────────────────────────┤
│  Namespace: symfony-ns                 │
│  ├─ Deployment: symfony-app (v1)       │
│  │  └─ Pod: php-nginx (N réplicas)     │
│  ├─ Deployment: mysql                  │
│  ├─ Service: nginx-service (ClusterIP) │
│  ├─ Service: mysql-service (ClusterIP) │
│  └─ Ingress: symfony-ingress (ALB)     │
│                                        │
│  Namespace: monitoring-ns              │
│  ├─ Prometheus, Grafana (Ingress)      │
│  ├─ Loki, Tempo                        │
│  ├─ NodeExporter, cAdvisor             │
│  └─ OTEL Collector                     │
│                                        │
│  Persistent Storage:                   │
│  ├─ EBS (MySQL, logs)                  │
│  └─ EFS (shared logs)                  │
└────────────────────────────────────────┘
         ↓
   ALB (AWS Application Load Balancer w/ sticky sessions)
   ├─ /health → nginx:80
   ├─ /grafana/* → grafana:3000
   └─ /* → nginx:80
         ↓
   http://<ALB-DNS>
```

## 🐛 Troubleshooting <a name="troubleshooting" />

### Local (Minikube)

```bash
# Minikube no inicia
minikube delete
minikube start --driver=docker

# Docker no funciona
sudo systemctl start docker
sudo usermod -aG docker $USER
newgrp docker

# No puedo acceder a symfony.local
cat /etc/hosts  # Verificar que existe la línea
# Si no existe, vuelve a ejecutar deploy-k8s.sh

# Limpiar todo
./deploy-k8s.sh cleanup local
minikube delete
```

### AWS (EKS)

```bash
# No puedo conectar a EKS
cd infra/main
aws eks --region eu-west-1 update-kubeconfig --name $(terraform output -raw cluster_name)
kubectl cluster-info

# ALB no funciona
kubectl get ingress -n symfony-ns -o yaml
kubectl logs -n kube-system -l app.kubernetes.io/name=aws-load-balancer-controller -f
kubectl logs -n kube-system -l app.kubernetes.io/name=aws-load-balancer-controller --tail=100

# Verificar IAM role
aws iam get-role-policy --role-name eks-alb-controller --policy-name AWSLoadBalancerControllerIAMPolicy

# Comprobar si los CRD están instalados
kubectl get crds | grep external

# Comprobar si los pods de external-secrets están funcionando
kubectl get pods -n external-secrets

# Comprobar las versiones soportadas del manifiesto secretstore
kubectl get crd secretstores.external-secrets.io -o jsonpath='{.spec.versions[*].name}'

# Cleanup (CUIDADO - Elimina toda la infraestrucutra)
./deploy-k8s.sh cleanup aws
# Verifica manualmente en AWS Console que se hayan eliminado:
# - VPC
# - NAT Gateways
# - Elastic IPs
# - Security Groups
# - EKS Node group
# - EC2 instances
# - EC2 Auto Scaling groups
# - EC2 Load balancers (elb)
# - EC2 volumes
# - EC2 target groups
# - EFS
# - Secrets Manager
# - Bucket S3
# - Dynamo DB
```

### Común

```bash
# Pods no despliegan
kubectl describe pod <pod-name> -n symfony-ns
kubectl describe pod -n symfony-ns deployment/symfony-app
kubectl logs <pod-name> -n symfony-ns

# Comprobar log del namespace
kubectl get events -n symfony-ns --sort-by='.lastTimestamp'

# Logs de un contenedor en concreto
kubectl logs -n symfony-ns <symfony-deployment> -c php-nginx-container

kubectl logs -n symfony-ns deployment/symfony-app
kubectl logs -n symfony-ns deployment/symfony-app -c php-nginx-container

# Obtener info de un ingress
kubectl describe ingress grafana-ingress -n monitoring-ns
```

## 📚 Documentación Adicional <a name="documentacion-adicional" />

- [Kubernetes Documentation](https://kubernetes.io/docs/)
- [Terraform AWS Provider](https://registry.terraform.io/providers/hashicorp/aws/latest/docs)
- [Kustomize](https://kustomize.io/)
- [Symfony Documentation](https://symfony.com/doc/)
- [Docker Documentation](https://docs.docker.com/)

## 📝 Notas Importantes <a name="notas-importantes" />

- **Seguridad**: Los archivos de secrets y terraform.tfvars están en `.gitignore`. NUNCA los hagas públicos.
- **Costes AWS**: Asegúrate de ejecutar `./deploy-k8s.sh cleanup aws` después de terminar para evitar costes no deseados.
- **Blue/Green**: El despliegue green mantiene 2 réplicas. Ajusta `replicas` en el deployment si es necesario.
- **Certificados**: Para producción, configura SSL/TLS en el ALB (recomendado usar AWS Certificate Manager).
- **Responsabilidad**: Este proyecto y los scripts incluidos se proporcionan tal cual. No me hago responsable del mal uso del código, errores de ejecución, pérdidas de datos o daños derivados directa o indirectamente del uso de este repositorio.
- **Entorno recomendado**: Para evitar problemas en tu máquina local y aislar dependencias, se recomienda ejecutar los scripts en una máquina virtual (VM) o entorno controlado.

## 👨‍🎓 Proyecto Educativo <a name="proyecto-educativo" />

Este proyecto es un ejemplo práctico de DevOps que incluye:

- ✅ Infraestructura como Código (Terraform)
- ✅ Contenedorización (Docker)
- ✅ Orquestación (Kubernetes)
- ✅ Automatización (GitHub Actions)
- ✅ Despliegue Blue/Green
- ✅ Monitorización (Prometheus, Grafana)
- ✅ Trazabilidad (Tempo, Loki)

## 🤝🏻 Licencia <a name="licencia" />

Distribuido bajo la Licencia MIT. Para más información, consulta el archivo LICENSE.
