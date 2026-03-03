# efs.tf
resource "aws_efs_file_system" "efs_volume" {
  creation_token = "${var.project_name}-efs-volume" # Podría ser también el nombre del cluster
  encrypted      = true
}

resource "aws_security_group" "efs_volume_sg" {
  name        = "${var.project_name}-efs-volume-sg"
  description = "Security group for EFS access from EKS"
  vpc_id      = module.vpc.vpc_id

  ingress {
    description     = "NFS from EKS nodes"
    from_port       = 2049
    to_port         = 2049
    protocol        = "tcp"
    cidr_blocks     = [var.vpc_cidr]
    # security_groups = [module.eks.node_security_group_id] # Mejor a nivel nodo que cluster, pero para no fallar, filtrar por VPC
    # security_groups = [module.eks.cluster_security_group_id] # Usar sólo cidr_blocks o security_groups, pero no ambos
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "aws_efs_mount_target" "efs_volume_mount" {
  count           = length(module.vpc.private_subnets)

  file_system_id  = aws_efs_file_system.efs_volume.id
  subnet_id       = module.vpc.private_subnets[count.index]
  security_groups = [aws_security_group.efs_volume_sg.id]
}

resource "aws_efs_access_point" "symfony_ap" {
  file_system_id = aws_efs_file_system.efs_volume.id

  posix_user {
    uid = 1000
    gid = 1000
  }

  root_directory {
    path = "/symfony"
    creation_info {
      owner_uid   = 1000
      owner_gid   = 1000
      permissions = "0755"
    }
  }
}

resource "kubernetes_storage_class" "symfony_sc" {
  metadata {
    name = "symfony-sc"
  }

  storage_provisioner = "efs.csi.aws.com"

  parameters = {
    provisioningMode = "efs-ap"
    fileSystemId     = aws_efs_file_system.efs_volume.id
    accessPointId    = aws_efs_access_point.symfony_ap.id
    directoryPerms = "0755"
    gidRangeStart  = "1000"
    gidRangeEnd    = "2000"
    uidRangeStart  = "1000"
    uidRangeEnd    = "2000"
  }

  # En producción debería ser Retain
  reclaim_policy        = "Delete"
  volume_binding_mode   = "WaitForFirstConsumer" # Si no se pone, el volumen se crea antes de que se asocie a un nodo y falla porque no puede acceder al EFS, con esto se espera a que se cree el pod y se asocie a un nodo antes de crear el volumen

  depends_on = [
    module.eks,
    aws_eks_addon.efs_csi_driver
  ]
}
