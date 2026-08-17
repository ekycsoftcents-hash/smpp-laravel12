FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git python3 python3-pip \
    && python3 -m pip install --break-system-packages --no-cache-dir telnetlib3 \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo_pgsql bcmath pcntl zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN chmod +x /var/www/html/scripts/provision_smpp_user.py
ENV COMPOSER_MAX_PARALLEL_HTTP=1 COMPOSER_PROCESS_TIMEOUT=900
RUN for attempt in 1 2 3 4 5; do composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction && exit 0; echo "Composer download failed; retrying in 15 seconds (attempt $attempt/5)"; sleep 15; done; exit 1
RUN chown -R www-data:www-data storage bootstrap/cache
RUN printf '%s\n' '<VirtualHost *:80>' 'DocumentRoot /var/www/html/public' '<Directory /var/www/html/public>' 'AllowOverride All' 'Require all granted' '</Directory>' '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf
EXPOSE 80
CMD ["apache2-foreground"]
