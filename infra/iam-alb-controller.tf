# iam-alb-controller.tf
locals {
  oidc_url = replace(module.eks.cluster_oidc_issuer_url, "https://", "")
}

# IAM Role y Policy para el AWS Load Balancer Controller, siguiendo las instrucciones oficiales de instalación: https://kubernetes-sigs.github.io/aws-load-balancer-controller/v2.4/deploy/installation/
# En lugar de crear un nuevo oidc provider, se usa el que crea module.eks
resource "aws_iam_role" "alb_controller" {
  name = "eks-alb-controller"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect = "Allow"
      Principal = {
        Federated = module.eks.oidc_provider_arn
      }
      Action = "sts:AssumeRoleWithWebIdentity"
      Condition = {
        StringEquals = {
          "${local.oidc_url}:sub" = "system:serviceaccount:kube-system:aws-load-balancer-controller"
        }
      }
    }]
  })

  depends_on = [
    module.eks
  ]
}

data "http" "alb_policy" {
  url = "https://raw.githubusercontent.com/kubernetes-sigs/aws-load-balancer-controller/main/docs/install/iam_policy.json"
}

resource "aws_iam_policy" "alb_controller" {
  name   = "AWSLoadBalancerControllerIAMPolicy"
  policy = data.http.alb_policy.response_body
}

resource "aws_iam_role_policy_attachment" "alb_attach" {
  role       = aws_iam_role.alb_controller.name
  policy_arn = aws_iam_policy.alb_controller.arn
}
