# s3-bucket.tf
resource "aws_s3_bucket" "tfstate_bucket" {
  bucket = "bucket-terraform-state-jln-35y728xstkvuwr2l457zw4uqz"
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
