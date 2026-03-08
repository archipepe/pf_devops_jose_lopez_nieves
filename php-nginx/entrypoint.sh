#!/bin/bash
# entrypoint.sh

set -e  # Salir si hay error

# Iniciar PHP-FPM
php-fpm8.3 -D
# sleep 3  # Damos tiempo para que inicie

# Iniciar Nginx
exec nginx -g 'daemon off;'
