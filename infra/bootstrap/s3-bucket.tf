# s3-bucket.tf
resource "random_id" "bucket_id" {
  byte_length = 8
}

resource "aws_s3_bucket" "tfstate_bucket" {
  bucket = "bucket-terraform-state-jln-${random_id.bucket_id.hex}"

  # Es necesario para hacer un cleanup completo
  force_destroy = true
}

# Dynamodb Table
resource "aws_dynamodb_table" "terraform_lock" {
  name         = "terraform-lock"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "LockID"

  attribute {
    name = "LockID"
    type = "S"
  }
}
