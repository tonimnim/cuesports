# CueSports Africa - Production Deployment
FROM dunglas/frankenphp:latest-php8.3

LABEL maintainer="CueSports Africa"

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Africa/Nairobi
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_PROCESS_TIMEOUT=600
ENV APP_ENV=production
ENV APP_DEBUG=false

# Install system dependencies + Node.js
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
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev)
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-dev

# Copy package.json for npm
COPY package.json package-lock.json ./

# Install npm dependencies
RUN npm ci

# Copy application files
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# Build Vite assets for Admin/Support dashboards
RUN npm run build

# Create log directory
RUN mkdir -p /var/log

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache \
    && chmod +x /app/docker/start.sh

# Railway provides PORT env variable
ENV PORT=8000
EXPOSE 8000

# Use startup script
CMD ["/app/docker/start.sh"]
