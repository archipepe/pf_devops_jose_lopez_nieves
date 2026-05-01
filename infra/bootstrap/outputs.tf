# outputs.tf
output "bucket_name" {
  description = "Nombre del bucket S3"
  value       = aws_s3_bucket.tfstate_bucket.bucket
}
