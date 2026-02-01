# CueSports Africa - Railway Deployment
FROM dunglas/frankenphp:latest-php8.3

LABEL maintainer="CueSports Africa"

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Africa/Nairobi
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_PROCESS_TIMEOUT=600
ENV APP_ENV=production
ENV APP_DEBUG=false

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        posix \
        bcmath \
        gd \
        opcache \
        intl \
        zip \
        sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies (no dev)
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-dev

# Copy application files
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# Railway provides PORT env variable
ENV PORT=8000
EXPOSE 8000

# Start Laravel Octane
CMD php artisan migrate --force && php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=${PORT:-8000}
