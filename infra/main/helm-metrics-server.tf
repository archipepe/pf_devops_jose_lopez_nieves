# helm-metrics-server.tf
# Metrics Server proporciona métricas de CPU/memoria necesarias para el HPA
# Sin Metrics Server, el HPA no funciona (no tiene datos para escalar)

resource "helm_release" "metrics_server" {
  name       = "metrics-server"
  namespace  = "kube-system"
  repository = "https://kubernetes-sigs.github.io/metrics-server/"
  chart      = "metrics-server"
  version    = "3.11.0"

  set = [
    {
      name  = "args[0]"
      value = "--kubelet-insecure-tls"
    },
    {
      name  = "args[1]"
      value = "--kubelet-preferred-address-types=InternalIP"
    }
  ]

  depends_on = [module.eks]
}
