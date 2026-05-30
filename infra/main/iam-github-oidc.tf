# iam-github-oidc.tf
# Configura GitHub OIDC para eliminar credenciales estáticas en AWS
# Permite que GitHub Actions asuma un rol IAM sin necesidad de AWS_ACCESS_KEY_ID/SECRET

# OIDC Provider para GitHub
# Este provider permite que GitHub Actions se autentique sin claves estáticas
resource "aws_iam_openid_connect_provider" "github" {
  url = "https://token.actions.githubusercontent.com"

  client_id_list = ["sts.amazonaws.com"]

  thumbprint_list = [
    "6938fd4d98bab03faadb97b34396831e3780aea1",  # Thumbprint actual de GitHub OIDC
    "1c3dd6897fac6b12c2f4b0d3850e4f2575ac1f26"   # Thumbprint de respaldo
  ]
}

# IAM Role para GitHub Actions
# Este role será asumido por GitHub Actions sin necesidad de credenciales de larga duración
resource "aws_iam_role" "github_actions_role" {
  name = "github-actions-role-${var.project_name}"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Principal = {
          Federated = aws_iam_openid_connect_provider.github.arn
        }
        Action = "sts:AssumeRoleWithWebIdentity"
        Condition = {
          StringEquals = {
            "token.actions.githubusercontent.com:aud" = "sts.amazonaws.com"
          }
          StringLike = {
            "token.actions.githubusercontent.com:sub" = "repo:${var.github_repository}:*"
          }
        }
      }
    ]
  })
}

# Policy: Permisos para actualizar kubeconfig y acceder a EKS
resource "aws_iam_role_policy" "github_eks_access" {
  name = "github-eks-access-policy"
  role = aws_iam_role.github_actions_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        # Permisos para actualizar kubeconfig
        Effect = "Allow"
        Action = [
          "eks:DescribeCluster",
          "eks:ListClusters"
        ]
        Resource = "arn:aws:eks:${var.aws_region}:${data.aws_caller_identity.current.account_id}:cluster/${module.eks.cluster_name}"
      },
      {
        # Permisos de IAM para ver si puede asumir el rol
        Effect = "Allow"
        Action = [
          "iam:GetRole"
        ]
        Resource = "${aws_iam_role.github_actions_role.arn}"
      }
    ]
  })
}

# Policy: Permisos en EKS (acceso como usuario root)
# En eks.tf ya se creó aws_eks_access_entry para root
resource "aws_eks_access_entry" "github_actions" {
  cluster_name      = module.eks.cluster_name
  principal_arn     = aws_iam_role.github_actions_role.arn
  type              = "STANDARD"
  kubernetes_groups = []
}

resource "aws_eks_access_policy_association" "github_actions" {
  cluster_name   = module.eks.cluster_name
  principal_arn  = aws_iam_role.github_actions_role.arn
  policy_arn     = "arn:aws:eks::aws:cluster-access-policy/AmazonEKSClusterAdminPolicy"
  access_scope {
    type = "cluster"
  }
}

# Output: ARN del role para usar en GitHub Actions
output "github_actions_role_arn" {
  description = "ARN del IAM Role para GitHub Actions (usar en workflow)"
  value       = aws_iam_role.github_actions_role.arn
}
