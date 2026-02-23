# Smart Parking - Full Stack
# PHP API + optional frontend build

FROM php:8.2-cli AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json ./

# Install dependencies (no dev for production)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application
COPY . .

# Generate autoload
RUN composer dump-autoload --optimize

# Default command (override in docker-compose)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
