# helm-alb-controller.tf
# ServiceAccount del ALB Controller (con IRSA)
resource "kubernetes_service_account" "alb_sa" {
  metadata {
    name      = "aws-load-balancer-controller"
    namespace = "kube-system"
    annotations = {
      "eks.amazonaws.com/role-arn" = aws_iam_role.alb_controller.arn
    }
  }
}

# Helm Release del AWS Load Balancer Controller
# Crea el IngressClass de nombre alb, no hace falta crearlo manualmente
resource "helm_release" "aws_load_balancer_controller" {
  name       = "aws-load-balancer-controller"
  namespace  = "kube-system"
  repository = "https://aws.github.io/eks-charts"
  chart      = "aws-load-balancer-controller"

  set = [
    {
        name  = "clusterName"
        value = module.eks.cluster_name
    },
    {
        name  = "serviceAccount.create"
        value = "false"
    },
    {
        name  = "serviceAccount.name"
        value = "aws-load-balancer-controller"
    },
    {
        name  = "region"
        value = var.aws_region
    },
    {
        name  = "vpcId"
        value = module.vpc.vpc_id
    }
  ]

  depends_on = [
    kubernetes_service_account.alb_sa
  ]
}
