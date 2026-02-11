#!/bin/bash
set -e

# Sustituir variables de entorno en la configuración de nginx
envsubst '${FASTCGI_HOST},${FASTCGI_PORT}' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf

# Iniciar nginx en foreground
exec nginx -g "daemon off;"
