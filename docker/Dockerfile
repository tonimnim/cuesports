# CueSports Africa - Laravel Production Dockerfile
# With Laravel Octane (FrankenPHP), Horizon, and all optimizations

FROM dunglas/frankenphp:latest-php8.3 AS base

LABEL maintainer="CueSports Africa"
LABEL description="CueSports Africa API with Laravel Octane"

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Africa/Lagos
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_PROCESS_TIMEOUT=600

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
    supervisor \
    cron \
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

# Install Node.js for Vite asset building
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install all dependencies (including dev for development builds)
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copy package files for npm
COPY package.json package-lock.json ./

# Install npm dependencies
RUN npm ci

# Copy application files
COPY . .

# Generate optimized autoloader and run post-install scripts
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# Build Vite assets for Inertia/React admin dashboard
RUN npm run build

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# ============================================
# Development Stage
# ============================================
FROM base AS development

ENV APP_ENV=local
ENV APP_DEBUG=true

# Dev dependencies already installed in base stage
RUN composer dump-autoload

# Expose port
EXPOSE 8000

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]

# ============================================
# Production Stage
# ============================================
FROM base AS production

ENV APP_ENV=production
ENV APP_DEBUG=false

# Remove dev dependencies for production
RUN composer install --no-dev --optimize-autoloader

# PHP production optimizations
COPY docker/php/php-production.ini /usr/local/etc/php/conf.d/99-production.ini

# Cache configurations
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy cron configuration
COPY docker/cron/laravel-cron /etc/cron.d/laravel-cron
RUN chmod 0644 /etc/cron.d/laravel-cron \
    && crontab /etc/cron.d/laravel-cron

# Expose port
EXPOSE 8000

# Start supervisor (manages Octane, Horizon, and Cron)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
