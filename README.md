# ALB Ingress Controller + ECR

```
┌─────────────────────────────────────────────────────────┐
│                     AWS                                 │
│  ┌─────────────────────────────────────────────────┐    │
│  │               ECR (Repositorio Público)         │    │
│  │  mysymfony/symfony-php:X.X                      │    │
│  │  mysymfony/symfony-nginx:X.X                    │    │
│  └────────────────────┬────────────────────────────┘    │
│                       │                                 │
│                       ▼                                 │
│  ┌─────────────────────────────────────────────────┐    │
│  │         EKS Cluster                             │    │
│  │  ┌────────────────────────────────────────────┐ │    │
│  │  │  Namespace: symfony-ns                     │ │    │
│  │  │  ┌────────────────────────────────────┐    │ │    │
│  │  │  │  Pod: symfony-app (Deployment)     │    │ │    │
│  │  │  │  Image: ECR público                │    │ │    │
│  │  │  └──────────────┬─────────────────────┘    │ │    │
│  │  │                 │                          │ │    │
│  │  │  ┌──────────────▼──────────────┐           │ │    │
│  │  │  │ Service: nginx-service      │           │ │    │
│  │  │  │ Type: ClusterIP             │           │ │    │
│  │  │  └──────────────┬──────────────┘           │ │    │
│  │  │                 │                          │ │    │
│  │  │  ┌──────────────▼──────────────┐           │ │    │
│  │  │  │ Ingress: symfony-ingress    │           │ │    │
│  │  │  │ Class: alb                  │           │ │    │
│  │  │  │ Controller: ALB Ingress     │           │ │    │
│  │  │  └──────────────┬──────────────┘           │ │    │
│  │  └─────────────────┼──────────────────────────┘ │    │
│  │                    │                            │    │
│  │  ALB Ingress Controller (kube-system)           │    │
│  └────────────────────┼────────────────────────────┘    │
│                       │                                 │
│                       ▼                                 │
│  ┌──────────────────────────────────────────────────┐   │
│  │     AWS Application Load Balancer                │   │
│  │  • Sticky Sessions: 86400 seg (24h)              │   │
│  │  • Health Check: / (cada 10seg)                  │   │
│  │  • Target Group: EKS nodes                       │   │
│  └──────────────────────────────────────────────────┘   │
│                       │                                 │
└───────────────────────┼─────────────────────────────────┘
                        │
                   HTTP (80)
                        │
          ┌─────────────▼─────────────┐
          │    Internet (cliente)     │
          │  http://<ALB-DNS>         │
          └───────────────────────────┘
```

### 1. Desplegar la infraestructura

```bash
cd ./infra

# Revisar cambios
terraform plan

# Aplicar
terraform apply
```

### 2. Subir imágenes al ECR

```bash
# Obtener credenciales ECR
aws ecr-public get-login-password --region us-east-1 | docker login --username AWS --password-stdin public.ecr.aws/l7n5d2e2

# Tagear imagen
docker tag mysymfony/php-nginx:7.0-prod public.ecr.aws/l7n5d2e2/mysymfony/php-nginx:7.0-prod

# Pushear
docker push public.ecr.aws/l7n5d2e2/mysymfony/php-nginx:7.0-prod
```

### 3. Desplegar en EKS

```bash
cd ./infra
aws eks --region $(terraform output -raw region) update-kubeconfig --name $(terraform output -raw cluster_name)

cd ./k8s
# kubectl apply -f namespaces/namespace-symfony.yaml && kubectl apply -f volumes/aws/pvc-symfony.yaml && kubectl apply -f deployments/aws/deployment-symfony.yaml && kubectl apply -f services/service-nginx.yaml && kubectl apply -f ingresses/aws/ingress-symfony.yaml

# TODO: hacer el equivalente para el delete más abajo
kubectl apply -f application/namespaces/namespace-symfony.yaml && \
kubectl apply -f application/configmaps/configmap-mysql.yaml && \
kubectl apply -f application/secretstores/aws/secretstore-symfony.yaml && \
kubectl apply -f application/externalsecrets/aws/externalsecret-app-symfony.yaml && \
kubectl apply -f application/externalsecrets/aws/externalsecret-database-symfony.yaml && \
kubectl apply -f application/externalsecrets/aws/externalsecret-mysql.yaml && \
kubectl apply -f application/externalsecrets/aws/externalsecret-user-queries.yaml && \
kubectl apply -f application/deployments/deployment-mysql.yaml && \
kubectl apply -f application/services/service-mysql.yaml && \
kubectl apply -f application/deployments/aws/deployment-symfony.yaml && \
kubectl apply -f application/services/service-nginx.yaml && \
kubectl apply -f application/ingresses/aws/ingress-symfony.yaml

#######################

kubectl apply -k observability/
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
# kubectl delete -f ingress/aws/ingress-symfony.yaml && kubectl delete -f services/service-nginx.yaml && kubectl delete -f deployments/aws/deployment-symfony.yaml && kubectl delete -f volumes/aws/pvc-symfony.yaml \
# && kubectl delete -f namespaces/namespace-symfony.yaml

kubectl delete -k observability/

#######################

kubectl delete -f application/ingresses/aws/ingress-symfony.yaml && \
kubectl delete -f application/services/service-mysql.yaml && \
kubectl delete -f application/services/service-nginx.yaml && \
kubectl delete -f application/deployments/deployment-mysql.yaml && \
kubectl delete -f application/deployments/aws/deployment-symfony.yaml && \
kubectl delete -f application/externalsecrets/aws/externalsecret-app-symfony.yaml && \
kubectl delete -f application/externalsecrets/aws/externalsecret-database-symfony.yaml && \
kubectl delete -f application/externalsecrets/aws/externalsecret-mysql.yaml && \
kubectl delete -f application/externalsecrets/aws/externalsecret-user-queries.yaml && \
kubectl delete -f application/secretstores/aws/secretstore-symfony.yaml && \
kubectl delete -f application/configmaps/configmap-mysql.yaml && \
kubectl delete -f application/namespaces/namespace-symfony.yaml
```

### 8. Destruir infraestructura

#############################################
QUEDARSE CON TODO EL JSON QUE TIENE TODOS LOS VALORES AL HACER DESTROY
#############################################

```bash
cd ./infra
terraform destroy
```
Para que vaya más rápido, ve mientras eliminando manualmente:
- EKS Node group
- EC2 instances
- EC2 Auto Scaling groups
- EC2 Load balancers (elb)
- EC2 volumes
- EFS
- ECR eliminar imagen (si no, no se podrá borrar el registro con el destroy)
- VPC
- Secrets Manager

### 9. Limpiar contextos de kubectl

```bash
kubectl config get-contexts && \
kubectl config use-context minikube

kubectl config delete-context arn:aws:eks:eu-west-1:961341509493:cluster/pf-devops-eks-2ZYjYdbP && \
kubectl config delete-cluster arn:aws:eks:eu-west-1:961341509493:cluster/pf-devops-eks-2ZYjYdbP && \
kubectl config delete-user    arn:aws:eks:eu-west-1:961341509493:cluster/pf-devops-eks-2ZYjYdbP
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
