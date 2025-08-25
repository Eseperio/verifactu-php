# syntax=docker/dockerfile:1
FROM php:8.1-cli

# Install system deps and PHP extensions required by the project
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git \
    unzip \
    ca-certificates \
    libxml2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libmagickwand-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) soap gd \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/* \
    && php -m | grep -E "(soap|dom|libxml|openssl|gd|imagick)" || true

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Default command runs PHPUnit; can be overridden by docker-compose
CMD ["php", "-v"]

