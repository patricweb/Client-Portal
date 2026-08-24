FROM php:8.4-apache

ARG NODE_MAJOR=22

RUN a2enmod rewrite headers expires \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-ikira.ini

COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-progress

COPY package.json ./
RUN npm install --ignore-scripts

COPY . .

RUN composer dump-autoload --optimize \
    && npm run build \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/ikira-entrypoint
RUN chmod +x /usr/local/bin/ikira-entrypoint

ENTRYPOINT ["ikira-entrypoint"]
CMD ["apache2-foreground"]
