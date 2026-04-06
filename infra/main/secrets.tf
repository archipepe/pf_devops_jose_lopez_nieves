# secrets.tf

# AWS Secrets Manager - Symfony APP_SECRET
resource "aws_secretsmanager_secret" "symfony_app_secret" {
  name = "symfony-app-secret"

  recovery_window_in_days = 0
}

resource "aws_secretsmanager_secret_version" "symfony_app_secret_value" {
  secret_id     = aws_secretsmanager_secret.symfony_app_secret.id
  secret_string = jsonencode({
    APP_SECRET = var.symfony_app_secret
  })
}

# AWS Secrets Manager - Symfony DATABASE_URL
resource "aws_secretsmanager_secret" "symfony_database_url" {
  name = "symfony-database-url"

  recovery_window_in_days = 0
}

resource "aws_secretsmanager_secret_version" "symfony_database_url_value" {
  secret_id     = aws_secretsmanager_secret.symfony_database_url.id
  secret_string = jsonencode({
    DATABASE_URL = var.symfony_database_url
  })
}

# AWS Secrets Manager - MySQL Root Password
resource "aws_secretsmanager_secret" "mysql_root_password" {
  name = "symfony-mysql-root-password"

  recovery_window_in_days = 0
}

resource "aws_secretsmanager_secret_version" "mysql_root_password_value" {
  secret_id     = aws_secretsmanager_secret.mysql_root_password.id
  secret_string = jsonencode({
    MYSQL_ROOT_PASSWORD = var.mysql_root_password
  })
}

# AWS Secrets Manager - User Queries SQL Script
resource "aws_secretsmanager_secret" "user_queries" {
  name = "symfony-user-queries"

  recovery_window_in_days = 0
}

resource "aws_secretsmanager_secret_version" "user_queries_value" {
  secret_id     = aws_secretsmanager_secret.user_queries.id

  secret_string = jsonencode({
    USER_QUERIES = var.user_queries
  })
}
