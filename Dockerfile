FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    bash \
    git \
    curl \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        intl \
        mbstring \
        bcmath \
        pcntl \
        curl \
        xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]