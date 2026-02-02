# =============================================================================
# CueSports Africa - Production Dockerfile
# =============================================================================
# Platform-agnostic Laravel Octane deployment
# Supports: Railway, Render, Fly.io, DigitalOcean, AWS, any Docker host
#
# Environment Variables:
#   CONTAINER_ROLE: web|octane|worker|scheduler (default: web)
#   PORT: HTTP port (default: 8000)
# =============================================================================

FROM dunglas/frankenphp:latest-php8.3

LABEL maintainer="CueSports Africa"
LABEL description="Production-ready Laravel Octane with FrankenPHP"

# -----------------------------------------------------------------------------
# Environment Configuration
# -----------------------------------------------------------------------------
ENV DEBIAN_FRONTEND=noninteractive \
    TZ=Africa/Nairobi \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_PROCESS_TIMEOUT=600 \
    APP_ENV=production \
    APP_DEBUG=false \
    PORT=8000 \
    CONTAINER_ROLE=web

# -----------------------------------------------------------------------------
# Install System Dependencies
# -----------------------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Core utilities
    git \
    curl \
    zip \
    unzip \
    # PHP extension dependencies
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
    # Process management
    supervisor \
    # Node.js for asset compilation
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    # Clean up
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# -----------------------------------------------------------------------------
# Install PHP Extensions
# -----------------------------------------------------------------------------
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
        --with-xpm \
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
    && docker-php-ext-enable redis

# -----------------------------------------------------------------------------
# Configure PHP for Production
# -----------------------------------------------------------------------------
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "realpath_cache_size=4096K" >> /usr/local/etc/php/conf.d/php.ini \
    && echo "realpath_cache_ttl=600" >> /usr/local/etc/php/conf.d/php.ini \
    && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/php.ini

# -----------------------------------------------------------------------------
# Install Composer
# -----------------------------------------------------------------------------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# -----------------------------------------------------------------------------
# Set Working Directory
# -----------------------------------------------------------------------------
WORKDIR /app

# -----------------------------------------------------------------------------
# Install PHP Dependencies (cached layer)
# -----------------------------------------------------------------------------
COPY composer.json composer.lock ./
RUN composer install \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-dev \
    --no-interaction

# -----------------------------------------------------------------------------
# Install Node Dependencies (cached layer)
# -----------------------------------------------------------------------------
COPY package.json package-lock.json ./
RUN npm ci --omit=dev

# -----------------------------------------------------------------------------
# Copy Application Code
# -----------------------------------------------------------------------------
COPY . .

# -----------------------------------------------------------------------------
# Build Application
# -----------------------------------------------------------------------------
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && npm run build

# -----------------------------------------------------------------------------
# Setup Supervisor
# -----------------------------------------------------------------------------
RUN mkdir -p /var/log/supervisor /var/run \
    && cp /app/docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# -----------------------------------------------------------------------------
# Set Permissions
# -----------------------------------------------------------------------------
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache \
    && chmod +x /app/docker/start.sh

# -----------------------------------------------------------------------------
# Health Check
# -----------------------------------------------------------------------------
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:${PORT}/api/health || exit 1

# -----------------------------------------------------------------------------
# Expose Port & Start
# -----------------------------------------------------------------------------
EXPOSE ${PORT}

# Graceful shutdown: match stopwaitsecs in supervisor
STOPSIGNAL SIGTERM

CMD ["/app/docker/start.sh"]
