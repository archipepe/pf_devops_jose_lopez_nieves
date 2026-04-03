# providers.tf
provider "aws" {
  region = var.aws_region
  default_tags {
    tags = {
        Project     = var.project_name
        Environment = var.environment
        ManagedBy   = "Terraform"
        CreatedBy   = "cursodevops"
        TTL         = "4h"  # Time to live, eliminar después de
    }
  }
}

data "aws_eks_cluster_auth" "cluster" {
  name = module.eks.cluster_name
}

provider "kubernetes" {
  host                   = module.eks.cluster_endpoint
  cluster_ca_certificate = base64decode(module.eks.cluster_certificate_authority_data)
  token                  = data.aws_eks_cluster_auth.cluster.token
}

provider "helm" {
  kubernetes = {
    host                   = module.eks.cluster_endpoint
    cluster_ca_certificate = base64decode(module.eks.cluster_certificate_authority_data)
    token                  = data.aws_eks_cluster_auth.cluster.token
  }
}

terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.47.0"
    }

    # Son providers estándares, pero se fija la versión para evitar problemas de compatibilidad
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6.1"
    }

    tls = {
      source  = "hashicorp/tls"
      version = "~> 4.0.5"
    }

    cloudinit = {
      source  = "hashicorp/cloudinit"
      version = "~> 2.3.4"
    }

    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.25.1"
    }

    helm = {
      source  = "hashicorp/helm"
      version = "~> 3.1.1"
    }
  }

  required_version = "~> 1.3"
}

# terraform {
#   backend "s3" {
#     bucket = "tfstate-bucket-0667121a1aa6b245"
#     key = "state/terraform.tfstate"
#     region = "eu-west-1"
#     dynamodb_table = "terraform-lock"
#     encrypt = true
#   }
# }
