# s3-bucket.tf
# # S3 Bucket
# resource "random_id" "bucket_id" {
#   byte_length = 8
# }

# # TODO: Cambiarlo a un nombre precedible
# resource "aws_s3_bucket" "tfstate_bucket" {
#   bucket = "tfstate-bucket-${random_id.bucket_id.hex}"

# #   tags = {
# #     Name = "${var.project_name}_TfstateBucket"
# #   }
# }

# # Dynamodb Table
# resource "aws_dynamodb_table" "terraform_lock" {
#   name         = "terraform-lock"
#   billing_mode = "PAY_PER_REQUEST"
#   hash_key     = "LockID"

#   attribute {
#     name = "LockID"
#     type = "S"
#   }
# }

# # Role
# data "aws_iam_policy_document" "ec2_assume_role" {
#     statement {
#       actions = ["sts:AssumeRole"]

#       principals {
#         type = "Service"
#         identifiers = ["ec2.amazonaws.com"]
#       }
#     }
# }

# resource "aws_iam_role" "ec2_role" {
#   name                = "EC2AccessS3"
#   assume_role_policy  = data.aws_iam_policy_document.ec2_assume_role.json
# }

# # Policy
# data "aws_iam_policy_document" "s3_access_policy" {
#     statement {
#         actions   = ["s3:*"]
#         resources = [
#           aws_s3_bucket.tfstate_bucket.arn,
#           "${aws_s3_bucket.tfstate_bucket.arn}/*"
#         ]
#         effect    = "Allow"
#     }
#     statement {
#         actions   = ["dynamodb:*"]
#         resources = [
#           aws_dynamodb_table.terraform_lock.arn
#         ]
#         effect    = "Allow"
#     }
# }

# resource "aws_iam_policy" "s3_policy" {
#   name    = "EC2S3Access"
#   policy  = data.aws_iam_policy_document.s3_access_policy.json
# }

# # Role Policy Attachment
# resource "aws_iam_policy_attachment" "attach_s3_policy" {
#   name        = "ec2_policy_attachment"
#   policy_arn  = aws_iam_policy.s3_policy.arn
#   roles       = [aws_iam_role.ec2_role.name]
# }
