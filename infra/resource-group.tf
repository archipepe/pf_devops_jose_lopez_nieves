# resource-group.tf
resource "aws_resourcegroups_group" "test_resources" {
  name = "${var.project_name}-rg-${var.environment}"
  
  resource_query {
    query = <<JSON
{
  "ResourceTypeFilters": ["AWS::AllSupported"],
  "TagFilters": [
    {
      "Key": "Project",
      "Values": ["${var.project_name}"]
    },
    {
      "Key": "Environment",
      "Values": ["${var.environment}"]
    }
  ]
}
JSON
  }
}
