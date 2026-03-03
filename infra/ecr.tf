# ecr.tf
provider "aws" {
  alias  = "us_east_1"
  region = "us-east-1"
}

resource "aws_ecrpublic_repository" "mysymfony-symfony-php" {
  provider = aws.us_east_1

  repository_name = "mysymfony/symfony-php"

  catalog_data {
    about_text        = "Repositorio PF DevOps JLN"
    description       = "Repositorio PF DevOps JLN"
  }
}

resource "aws_ecrpublic_repository" "mysymfony-symfony-nginx" {
  provider = aws.us_east_1

  repository_name = "mysymfony/symfony-nginx"

  catalog_data {
    about_text        = "Repositorio PF DevOps JLN"
    description       = "Repositorio PF DevOps JLN"
  }
}
