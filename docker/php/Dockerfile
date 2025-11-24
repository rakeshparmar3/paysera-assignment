# docker/php/Dockerfile
FROM php:8.2-fpm

ARG USER_ID=637
ARG GROUP_ID=48

ENV DEBIAN_FRONTEND=noninteractive

# Install system/build dependencies, PHP build deps and libs required by extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    git \
    curl \
    pkg-config \
    build-essential \
    autoconf \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    zlib1g-dev \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd intl sockets zip \
    \
    # Install pecl extensions (redis, xdebug) and enable them
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    \
    # Cleanup build deps we don't need at runtime to reduce image size
    && apt-get purge -y --auto-remove build-essential autoconf pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN groupadd -g ${GROUP_ID} rakesh \
    && useradd -m -u ${USER_ID} -g ${GROUP_ID} rakesh

# Set working directory
WORKDIR /var/www

# Copy application
COPY . /var/www

# Ensure proper ownership (optional but helpful)
RUN chown -R ${USER_ID}:${GROUP_ID} /var/www

# Switch to non-root user
USER rakesh

EXPOSE 9000
CMD ["php-fpm"]
