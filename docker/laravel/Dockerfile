FROM php:8.3-fpm-alpine

# Cài đặt các gói phụ thuộc hệ thống cần thiết
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    linux-headers

# Cài đặt các PHP extensions: pdo_mysql, bcmath, gd, zip (như yêu cầu)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Cài đặt Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Cài đặt Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Thiết lập thư mục làm việc
WORKDIR /var/www/html

# Phân quyền cho www-data
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
