FROM php:8.3-apache-bookworm
WORKDIR /var/www/html
ENV PORT=8080 APP_ENV=production APP_DEBUG=false SIGNIA_SCHEDULER_ENABLED=false
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip ffmpeg supervisor default-mysql-client python3 && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install -j2 pdo_mysql mbstring zip intl gd opcache pcntl && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/stories && ln -s /data/media/app storage/app && ln -s /data/media/covers public/stories/covers && ln -s /data/media/app/public public/storage && composer dump-autoload --no-dev --optimize && chown -R www-data:www-data storage bootstrap/cache
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/signia.ini
COPY deploy/supervisord.conf /etc/supervisor/conf.d/signia.conf
EXPOSE 8080
CMD ["sh", "/var/www/html/deploy/start.sh"]
