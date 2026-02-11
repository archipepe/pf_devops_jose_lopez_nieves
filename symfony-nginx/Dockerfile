FROM nginx:1.25

# Instalar gettext que contiene envsubst
RUN apt-get update && apt-get install -y gettext-base && rm -rf /var/lib/apt/lists/*

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf.template
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/entrypoint.sh"]
