# ALB Ingress Controller + ECR

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                    AWS                                              │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                          ECR (Repositorio Público)                           │   │
│  │             mysymfony/ubuntu:X.X  │  mysymfony/php-nginx:X.X                 │   │
│  └───────────────────────────────────┬──────────────────────────────────────────┘   │
│                                      │                                              │
│                                      ▼                                              │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                            EKS Cluster                                       │   │
│  │                                                                              │   │
│  │  ┌────────────────────────────────────────────────────────────────────────┐  │   │
│  │  │                     Namespace: symfony-ns                              │  │   │
│  │  │  ┌──────────────────────────────────────────────────────────────────┐  │  │   │
│  │  │  │  Deployments                                                     │  │  │   │
│  │  │  │  ┌─────────────────────┐  ┌─────────────────────┐                │  │  │   │
│  │  │  │  │  symfony-deployment │  │   mysql-deployment  │                │  │  │   │
│  │  │  │  │  (php-nginx)        │  │   (mysql:8.0)       │                │  │  │   │
│  │  │  │  │  Port: 80           │  │   Port: 3306        │                │  │  │   │
│  │  │  │  └──────────┬──────────┘  └──────────┬──────────┘                │  │  │   │
│  │  │  │             │                        │                           │  │  │   │
│  │  │  │  Services   │                        │                           │  │  │   │
│  │  │  │  ┌──────────▼──────────┐  ┌──────────▼──────────┐                │  │  │   │
│  │  │  │  │  nginx-service      │  │  mysql-service      │                │  │  │   │
│  │  │  │  │  Type: ClusterIP    │  │  Type: ClusterIP    │                │  │  │   │
│  │  │  │  │  Port: 80 → 80      │  │  Port: 3306         │                │  │  │   │
│  │  │  │  └──────────┬──────────┘  └─────────────────────┘                │  │  │   │
│  │  │  │             │                                                    │  │  │   │
│  │  │  │  Ingress    │                                                    │  │  │   │
│  │  │  │  ┌──────────▼──────────┐                                         │  │  │   │
│  │  │  │  │  symfony-ingress    │                                         │  │  │   │
│  │  │  │  │  Class: alb         │                                         │  │  │   │
│  │  │  │  │  Controller: ALB    │                                         │  │  │   │
│  │  │  │  └─────────────────────┘                                         │  │  │   │
│  │  │  └──────────────────────────────────────────────────────────────────┘  │  │   │
│  │  └────────────────────────────────────────────────────────────────────────┘  │   │
│  │                                                                              │   │
│  │  ┌────────────────────────────────────────────────────────────────────────┐  │   │
│  │  │                     Namespace: monitoring-ns                           │  │   │
│  │  │  ┌──────────────────────────────────────────────────────────────────┐  │  │   │
│  │  │  │  Deployments/Services                                            │  │  │   │
│  │  │  │  ┌──────────────┐  ┌──────────┐  ┌─────────┐  ┌───────────┐      │  │  │   │
│  │  │  │  │otel-collector│  │  tempo   │  │  loki   │  │prometheus │      │  │  │   │
│  │  │  │  │  Port:4317   │  │ Port:4317│  │Port:3100│  │ Port:9090 │      │  │  │   │
│  │  │  │  │  Port:4318   │  │ Port:3200│  │         │  │           │      │  │  │   │
│  │  │  │  │  Port:9464   │  │          │  │         │  │           │      │  │  │   │
│  │  │  │  └──────────────┘  └──────────┘  └─────────┘  └───────────┘      │  │  │   │
│  │  │  │  ┌────────────┐                                                  │  │  │   │
│  │  │  │  │  grafana   │                                                  │  │  │   │
│  │  │  │  │  Port:3000 │                                                  │  │  │   │
│  │  │  │  └────────────┘                                                  │  │  │   │
│  │  │  └──────────────────────────────────────────────────────────────────┘  │  │   │
│  │  │  ┌──────────────────────────────────────────────────────────────────┐  │  │   │
│  │  │  │  DaemonSets/Services                                             │  │  │   │
│  │  │  │  ┌────────────┐           ┌──────────────┐                       │  │  │   │
│  │  │  │  │ node-      │           │   cAdvisor   │                       │  │  │   │
│  │  │  │  │ exporter   │           │  Port:8080   │                       │  │  │   │
│  │  │  │  │ Port:9100  │           │  (DaemonSet) │                       │  │  │   │
│  │  │  │  └────────────┘           └──────────────┘                       │  │  │   │
│  │  │  └──────────────────────────────────────────────────────────────────┘  │  │   │
│  │  │  ┌──────────────────────────────────────────────────────────────────┐  │  │   │
│  │  │  │                    Ingress                                       │  │  │   │
│  │  │  │  ┌─────────────────────┐                                         │  │  │   │
│  │  │  │  │  grafana-ingress    │                                         │  │  │   │
│  │  │  │  │  Class: alb         │                                         │  │  │   │
│  │  │  │  │  Controller: ALB    │                                         │  │  │   │
│  │  │  │  └─────────────────────┘                                         │  │  │   │
│  │  │  └──────────────────────────────────────────────────────────────────┘  │  │   │
│  │  └────────────────────────────────────────────────────────────────────────┘  │   │
│  │                                                                              │   │
│  │  ALB Ingress Controller (kube-system)                                        │   │
│  └─────────────────────────────────┼────────────────────────────────────────────┘   │
│                                    │                                                │
│                                    ▼                                                │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                    AWS Application Load Balancer                             │   │
│  │  • Sticky Sessions: 3600 seg                                                 │   │
│  │  • Health Check: /health (cada 10seg)                                        │   │
│  │  • Target Group: EKS nodes                                                   │   │
│  │  • Reglas:                                                                   │   │
│  │    - /grafana/* → monitoring-ns/grafana:3000                                 │   │
│  │    - /* → symfony-ns/nginx-service:80                                        │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
│                                    │                                                │
└────────────────────────────────────┼────────────────────────────────────────────────┘
                                     │
                                HTTP (80)
                                     │
                    ┌────────────────▼────────────────┐
                    │         Internet                │
                    │  http://<ALB-DNS>               │
                    │  http://<ALB-DNS>/grafana       │
                    └─────────────────────────────────┘
```

### 1. Desplegar la infraestructura

```bash
cd <project_root>/infra/bootstrap

# Inicializar
# Se asume que estos cambios se guardan en local pero no contienen información sensible
terraform init

# Aplicar
terraform apply [-auto-approve]

# Una vez creados el bucket y la tabla, desplegamos el proyecto principal
cd <project_root>/infra/main

# Inicializar Terraform con estado remoto y cifrado
terraform init \
  -backend-config="bucket=bucket-terraform-state-jln-35y728xstkvuwr2l457zw4uqz" \
  -backend-config="key=main/terraform.tfstate" \
  -backend-config="region=eu-west-1" \
  -backend-config="dynamodb_table=terraform-lock" \
  -backend-config="encrypt=true"

# Aplicar
terraform apply [-auto-approve]
```

### 2. Subir imágenes al ECR

```bash
# Obtener credenciales ECR
aws ecr-public get-login-password --region us-east-1 | docker login --username AWS --password-stdin public.ecr.aws/l7n5d2e2

# Taguear imagen ubuntu
docker tag mysymfony/ubuntu:5.1-prod public.ecr.aws/l7n5d2e2/mysymfony/ubuntu:5.1-prod

# Pushear
docker push public.ecr.aws/l7n5d2e2/mysymfony/ubuntu:5.1-prod

# Taguear imagen php-nginx
docker tag mysymfony/php-nginx:7.1-prod public.ecr.aws/l7n5d2e2/mysymfony/php-nginx:7.1-prod

# Pushear
docker push public.ecr.aws/l7n5d2e2/mysymfony/php-nginx:7.1-prod
```

### 3. Desplegar en EKS

```bash
cd <project_root>/infra/main
aws eks --region $(terraform output -raw region) update-kubeconfig --name $(terraform output -raw cluster_name)

cd <project_root>/k8s

# Esto despliega application y observability
kubectl apply -k overlays/aws
```

### 4. Verificar funcionamiento desde el pod

```bash
kubectl get pods -n symfony-ns
kubectl exec -it <pod> -n symfony-ns -- bash
curl http://localhost
```

### 5. Verificar funcionamiento desde el nodo

```bash
kubectl get services -n symfony-ns
kubectl run debug-pod --image=nicolaka/netshoot:latest -it --rm --restart=Never -- /bin/bash
curl http://<IP SERVICIO>
```

### 6. Verificar ALB Controller instalado

```bash
kubectl get pods -n kube-system -l app.kubernetes.io/name=aws-load-balancer-controller
# Debería mostrar 2 pods corriendo

# Obtener DNS del ALB
kubectl get ingress -n symfony-ns
# Buscar: Address: y probar a acceder desde esa URL
# NAMESPACE    NAME              CLASS   HOSTS   ADDRESS                                            PORTS   AGE
# symfony-ns   symfony-ingress   alb     *       symfony-alb-89347301.eu-west-1.elb.amazonaws.com   80      10m

# Esperar a que ALB esté listo (puede tardar 1-2 minutos)
ALB_URL=$(kubectl get ingress symfony-ingress -n symfony-ns -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')

# Test HTTP
curl http://$ALB_URL

# Ver logs del pod en tiempo real [-f]
kubectl logs -n symfony-ns deployment/symfony-app -f
```

### 7. Eliminar despliegue

```bash
kubectl delete -k overlays/aws
```

### 8. Destruir infraestructura

```bash
# TODO
# Eliminar las imágenes del ECR antes

cd <project_root>/infra/main
terraform destroy

# TODO
# Eliminar la carpeta main del bucket antes

cd <project_root>/infra/bootstrap
terraform destroy
```

Para que vaya más rápido, ve mientras eliminando manualmente:
- EKS Node group
- EC2 instances
- EC2 Auto Scaling groups
- EC2 Load balancers (elb)
- EC2 volumes
- EC2 target groups
- EFS
- ECR eliminar imagen (si no, no se podrá borrar el registro con el destroy)
- VPC
- Secrets Manager
- Bucket S3
- Dynamo DB

### 9. Limpiar contextos de kubectl

```bash
kubectl config get-contexts && \
kubectl config use-context minikube

kubectl config delete-context arn:aws:eks:eu-west-1:961341509493:cluster/pf-devops-eks-OM8HCqEO && \
kubectl config delete-cluster arn:aws:eks:eu-west-1:961341509493:cluster/pf-devops-eks-OM8HCqEO && \
kubectl config delete-user    arn:aws:eks:eu-west-1:961341509493:cluster/pf-devops-eks-OM8HCqEO
```

### Verificaciones importantes y troubleshooting

### Comprobar log del namespace
```bash
kubectl get events -n symfony-ns --sort-by='.lastTimestamp'
```

### ALB Ingress Controller activo
```bash
kubectl logs -n kube-system -l app.kubernetes.io/name=aws-load-balancer-controller -f
```

### Pod en estado Pending
```bash
kubectl describe pod -n symfony-ns deployment/symfony-app
# Ver sección "Events" para errores de imagen o recursos
```

### Logs de un contenedor en concreto
```bash
kubectl logs -n symfony-ns symfony-deployment-74fdcdbd99-bs7xf -c init-code
kubectl logs -n symfony-ns symfony-deployment-f4dd96cf5-fmtcj -c php-nginx-container

kubectl logs -n symfony-ns deployment/symfony-app
kubectl logs -n symfony-ns deployment/symfony-app -c php-nginx-container
```

### ALB no se crea
```bash
# Ver logs del ALB Controller
kubectl logs -n kube-system -l app.kubernetes.io/name=aws-load-balancer-controller --tail=100

# Verificar IAM role
aws iam get-role-policy --role-name eks-alb-controller --policy-name AWSLoadBalancerControllerIAMPolicy
```

### Obtener info de un ingress
```bash
kubectl describe ingress grafana-ingress -n monitoring-ns
```

### Comprobar si los CRD están instalados
```bash
kubectl get crds | grep external
```

### Comprobar si los pods de external-secrets están funcionando
```bash
kubectl get pods -n external-secrets
```

### Comprobar las versiones soportadas del manifiesto secretstore
```bash
kubectl get crd secretstores.external-secrets.io -o jsonpath='{.spec.versions[*].name}'
```
