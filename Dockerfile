FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
