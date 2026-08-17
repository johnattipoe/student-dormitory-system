FROM php:8.2-apache

# Enable Apache rewrite
RUN a2enmod rewrite

# Install required packages
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy Composer files first
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

# Copy application
COPY . .

# Apache serves public/
RUN sed -i \
    's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' \
    /etc/apache2/sites-available/000-default.conf

# Allow .htaccess
RUN printf '%s\n' \
    '<Directory /var/www/html/public>' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    > /etc/apache2/conf-available/student-dormitory.conf

RUN a2enconf student-dormitory

# Render provides PORT
ENV PORT=10000

RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf

RUN sed -i \
    's/:80>/:10000>/' \
    /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["apache2-foreground"]