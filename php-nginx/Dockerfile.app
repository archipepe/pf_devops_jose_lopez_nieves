# Dockerfile.app
FROM mysymfony/ubuntu:24.04-1.0

# Crear un usuario no root para ejecutar la aplicación
RUN addgroup --system symfonyapp && adduser --system --ingroup symfonyapp symfonyapp

# Copiar el código fuente
WORKDIR /var/www/html
RUN rm -rf /var/www/html/*
COPY symfony-app/ .

RUN php /usr/local/bin/composer install

RUN chown -R symfonyapp:symfonyapp . && chmod -R 755 .
RUN chown -R www-data:www-data public var && chmod -R 755 /var/www/html/var

# TODO
# USER symfonyapp

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:80 || exit 1
