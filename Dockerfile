FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libzip-dev \
    sqlite-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    mbstring \
    zip \
    pcntl \
    posix

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Permissions
RUN chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
