FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libonig-dev libcurl4-openssl-dev \
 && docker-php-ext-install pdo_mysql mbstring intl opcache curl \
 && a2enmod rewrite headers \
 && printf '%s\n' '<Directory /var/www/html/public>' 'AllowOverride All' 'Require all granted' '</Directory>' > /etc/apache2/conf-available/laravel-public.conf \
 && a2enconf laravel-public \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
 && chown -R www-data:www-data storage bootstrap/cache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
EXPOSE 80
CMD ["apache2-foreground"]
