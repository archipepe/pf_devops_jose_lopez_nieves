# Dockerfile.app
FROM mysymfony/ubuntu:24.04-5.0-prod

# Copiar el código fuente
WORKDIR /var/www/html
RUN rm -rf /var/www/html/*
COPY symfony-app/ .

RUN chown -R ubuntu:ubuntu . && chmod -R 755 .

# Cambiar al usuario ubuntu
USER ubuntu

RUN php /usr/local/bin/composer install --optimize-autoloader

HEALTHCHECK --interval=300s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:80/health || exit 1
