FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    curl unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHPMailer
RUN mkdir -p /var/www/html && composer require --no-interaction --working-dir=/var/www/html phpmailer/phpmailer

RUN echo "upload_max_filesize = 512M" >> /usr/local/etc/php/php.ini \
 && echo "post_max_size = 512M"       >> /usr/local/etc/php/php.ini \
 && echo "memory_limit = 256M"        >> /usr/local/etc/php/php.ini

WORKDIR /var/www/html