# iam-efs-csi-driver.tf
# Según https://medium.com/@chinmay13/kubernetes-storage-playlist-part-2-implementing-amazon-efs-storage-with-eks-using-terraform-and-86ada019e1ba
resource "aws_iam_policy" "efs_csi_driver_policy" {
  name        = "${module.eks.cluster_name}-efs-csi-driver-policy"
  description = "Policy for EFS CSI driver"

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "elasticfilesystem:DescribeAccessPoints",
          "elasticfilesystem:DescribeFileSystems",
          "elasticfilesystem:DescribeMountTargets",
          "ec2:DescribeAvailabilityZones"
        ]
        Resource = "*"
      },
      {
        Effect = "Allow"
        Action = [
          "elasticfilesystem:CreateAccessPoint",
          "elasticfilesystem:TagResource"
        ]
        Resource = "*"
      },
      {
        Effect = "Allow"
        Action = [
          "elasticfilesystem:DeleteAccessPoint"
        ]
        Resource = "*"
        Condition = {
          StringEquals = {
            "elasticfilesystem:AccessedViaMountTarget" = "true"
          }
        }
      }
    ]
  })
}

resource "aws_iam_role" "efs_csi_driver_role" {
  name = "eks-efs-csi-driver-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Principal = {
          Service = "pods.eks.amazonaws.com"
        }
        Action = [
          "sts:AssumeRole",
          "sts:TagSession"
        ]
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "efs_csi_attach" {
  role       = aws_iam_role.efs_csi_driver_role.name
  policy_arn = aws_iam_policy.efs_csi_driver_policy.arn
}

# Se muestra en Acces entries dentro del cluster, pero no en el apartado correspondiente dentro del EFS CSI Addon
# Esto crea la service account dentro de K8s, no hace falta crearlo manualmente
resource "aws_eks_pod_identity_association" "efs_csi_driver" {
  cluster_name    = module.eks.cluster_name
  namespace       = "kube-system"
  service_account = "efs-csi-controller-sa"
  role_arn        = aws_iam_role.efs_csi_driver_role.arn
}
