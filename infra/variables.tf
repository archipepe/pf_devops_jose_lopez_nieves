# variables.tf
variable "aws_region" {
  description = "Región de AWS"
  type        = string
  default     = "eu-west-1"
}

variable "project_name" {
  default = "pf-devops"
}

variable "environment" {
  default = "test"
}

variable "vpc_cidr" {
  description = "CIDR para la VPC"
  default = "10.0.0.0/16"
}

variable "subnet_a_cidr" {
  description = "CIDR para la subred a (pública)"
  default = "10.0.1.0/24"
}

variable "subnet_b_cidr" {
  description = "CIDR para la subred b (pública)"
  default = "10.0.2.0/24"
}

variable "subnet_c_cidr" {
  description = "CIDR para la subred c (privada)"
  default = "10.0.3.0/24"
}

variable "subnet_d_cidr" {
  description = "CIDR para la subred d (privada)"
  default = "10.0.4.0/24"
}
