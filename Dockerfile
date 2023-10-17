FROM composer:2.1.3 AS composer

WORKDIR /var/www

COPY . ./
COPY .ssh/ /root/.ssh/
RUN chmod 600 ~/.ssh/*

RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts --optimize-autoloader --ignore-platform-reqs --no-dev

FROM registry.ibt.ru:5050/php82:1.6

WORKDIR /var/www

COPY --from=composer /var/www/ ./
COPY supervisord.conf /etc/supervisor/conf.d/workers.conf

ENV NGINX_WEB_ROOT=/var/www/public

EXPOSE 80
CMD ["/usr/bin/supervisord",  "-c",  "/etc/supervisor/supervisord.conf"]CMD ["sh", "-c", "/run.sh"]
