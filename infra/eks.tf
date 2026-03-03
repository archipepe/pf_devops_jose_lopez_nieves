# eks.tf
locals {
  cluster_name = "${var.project_name}-eks-${random_string.suffix.result}"
}

resource "random_string" "suffix" {
  length  = 8
  special = false
}

module "eks" {
  source  = "terraform-aws-modules/eks/aws"
  version = "20.8.5"

  cluster_name    = local.cluster_name
  # Misma versión que kubectl en mi máquina local o sólo una de diferencia
  cluster_version = "1.35"

  cluster_endpoint_public_access           = true
  enable_cluster_creator_admin_permissions = true

  vpc_id     = module.vpc.vpc_id
  subnet_ids = module.vpc.private_subnets

  eks_managed_node_group_defaults = {
    ami_type = "AL2023_x86_64_STANDARD"
    # ami_type = "AL2023_ARM_64_STANDARD" # ARM es más barato, pero me ha dado problemas con las imágenes
  }

  eks_managed_node_groups = {
    one = {
      name = "node-group-1"

      # instance_types = ["t4g.medium"] # ARM_64: A partir de poner EFS, hay que subir a medium mínimo, ya que se ejecutan más pods
      instance_types = ["t3.medium"] # x86_64: A partir de poner EFS, hay que subir a medium mínimo, ya que se ejecutan más pods
      min_size     = 2
      max_size     = 2
      desired_size = 2
    }
  }
}

# Crea explícitamente una entrada de acceso para el usuario root de la consola de AWS, aunque no es una práctica recomendada para producción, es útil para poder ver lo que vas creando en la consola
data "aws_caller_identity" "current" {}

resource "aws_eks_access_entry" "aws_root_user" {
  cluster_name  = module.eks.cluster_name
  principal_arn = "arn:aws:iam::${data.aws_caller_identity.current.account_id}:root"
  type          = "STANDARD"
}

resource "aws_eks_access_policy_association" "aws_root_user" {
  cluster_name   = module.eks.cluster_name
  principal_arn  = "arn:aws:iam::${data.aws_caller_identity.current.account_id}:root"
  policy_arn     = "arn:aws:eks::aws:cluster-access-policy/AmazonEKSClusterAdminPolicy"
  access_scope {
    type = "cluster"
  }
}

# TODO: funciona pero no se ve la asociación dentro del EFS CSI Addon, sólo desde Access entries dentro del clúster
# Mirar https://registry.terraform.io/modules/terraform-aws-modules/eks-pod-identity/aws/latest#aws-efs-csi-driverhttpsgithubcomkubernetes-sigsaws-efs-csi-driver
resource "aws_eks_addon" "efs_csi_driver" {
  cluster_name = module.eks.cluster_name
  addon_name   = "aws-efs-csi-driver"

  resolve_conflicts_on_create = "OVERWRITE"
  resolve_conflicts_on_update = "OVERWRITE"

  # Asegurarse de que este addon se crea después del rol IAM y la pod identity association
  depends_on = [
    aws_eks_addon.pod_identity_agent,
    aws_iam_role_policy_attachment.efs_csi_attach,
    aws_eks_pod_identity_association.efs_csi_driver
  ]
}

# Si se usa pod identity association, hay que instalar también el pod identity agent
resource "aws_eks_addon" "pod_identity_agent" {
  cluster_name = module.eks.cluster_name
  addon_name   = "eks-pod-identity-agent"

  resolve_conflicts_on_create = "OVERWRITE"
  resolve_conflicts_on_update = "OVERWRITE"

  depends_on = [module.eks]
}
