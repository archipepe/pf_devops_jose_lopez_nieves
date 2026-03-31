# ebs.tf
resource "aws_ebs_volume" "ebs_volume" {
  availability_zone = var.aws_ebs_az
  size              = 1 # GB
  type              = "gp3"
  encrypted         = true

  # Configure IOPS for gp3, io1, io2 volumes
  iops = 3000

  # Configure throughput for gp3 volumes
  throughput = 125

  depends_on = [ module.eks, module.vpc ]
}

resource "kubernetes_storage_class" "mysql_sc" {
  metadata {
    name = "mysql-sc"
  }

  storage_provisioner = "ebs.csi.aws.com"

  parameters = {
    type    = "gp3"
    fsType  = "ext4"
  }

  # En producción debería ser Retain
  reclaim_policy        = "Delete"
  volume_binding_mode   = "WaitForFirstConsumer" # Si no se pone, el volumen se crea antes de que se asocie a un nodo y falla porque no puede acceder al EBS, con esto se espera a que se cree el pod y se asocie a un nodo antes de crear el volumen

  depends_on = [
    module.eks,
    aws_eks_addon.ebs_csi_driver
  ]
}
