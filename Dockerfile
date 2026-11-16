FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    nginx bash libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql mbstring gd

RUN mkdir -p /run/nginx /var/www/html
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/zz-custom.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY . /var/www/html

RUN chmod +x /usr/local/bin/entrypoint.sh && chown -R www-data:www-data /var/www/html
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
