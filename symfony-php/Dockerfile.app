FROM mysymfony/php:8.2-fpm-1.0

WORKDIR /app
COPY app/. .

RUN composer install --optimize-autoloader

CMD ["php-fpm"]
