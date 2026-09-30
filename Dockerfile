# PHP 8.3 + Apache. Минимум PHP по composer.lock - 8.2 (illuminate/database ^12).
FROM php:8.3-apache

# Расширения, которых нет в образе, но нужны проекту:
#   pdo_pgsql - подключение к PostgreSQL
#   gd, zip   - требуются phpoffice/phpspreadsheet (выгрузка в Excel)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip gd \
    && rm -rf /var/lib/apt/lists/*

# Боевой php.ini: ошибки не показываются пользователю, а пишутся в лог (docker compose logs app)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Закрываем от веб-доступа служебные файлы (vendor, db, tests, .sql, composer.* и т.д.)
COPY docker/apache-security.conf /etc/apache2/conf-available/app-security.conf
RUN a2enconf app-security

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Сначала только composer-файлы: слой с зависимостями кэшируется, пока они не менялись
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

COPY . .
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
