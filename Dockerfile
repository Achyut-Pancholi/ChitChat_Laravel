FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Install dependencies, add PPA for PHP 8.2, and install PHP + tools
RUN apt-get update && apt-get install -y \
    software-properties-common \
    curl \
    unzip \
    supervisor \
    nginx \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update && apt-get install -y \
    php8.2 \
    php8.2-cli \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-pgsql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-tokenizer \
    php8.2-bcmath \
    php8.2-gd \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create a stub .env if not present (real values come from Render env vars at runtime)
RUN test -f .env || cp .env.example .env && php artisan key:generate --ansi

# Accept VITE_ vars as build args (must be available at npm build time, not just runtime)
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY
ENV VITE_REVERB_HOST=$VITE_REVERB_HOST
ENV VITE_REVERB_PORT=$VITE_REVERB_PORT
ENV VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

# Install Node dependencies and build frontend assets (generates public/build/manifest.json)
RUN npm ci && npm run build

# Set folder permissions and create php-fpm socket dir
RUN chmod -R 775 storage bootstrap/cache public/build \
    && chown -R www-data:www-data storage bootstrap/cache public/build

RUN mkdir -p /run/php

# Copy nginx config
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Copy supervisord config (runs nginx + php-fpm + reverb together)
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8080

CMD ["sh", "-c", "\
  php artisan config:cache && \
  php artisan route:cache && \
  php artisan view:cache && \
  php artisan migrate --force && \
  /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf"]
