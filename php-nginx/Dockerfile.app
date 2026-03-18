# Dockerfile.app
FROM mysymfony/ubuntu:24.04-3.0-debug

# Copiar el código fuente
WORKDIR /var/www/html
RUN rm -rf /var/www/html/*
COPY symfony-app/ .

RUN chown -R symfonyapp:symfonyapp . && chmod -R 755 .

# Cambiar al usuario symfonyapp
USER symfonyapp

RUN php /usr/local/bin/composer install

# TODO: Comentado hasta que se terminen las pruebas del carrito
# HEALTHCHECK --interval=300s --timeout=5s --start-period=5s --retries=3 \
#     CMD curl -f http://localhost:80/health || exit 1
