# 1. Lấy nền tảng PHP 8.3 CLI để chạy Laravel Octane + OpenSwoole
FROM php:8.3-cli

# 2. Khai báo biến user để đồng bộ với máy Host
ARG user=khang
ARG uid=1000

ENV COMPOSER_ALLOW_SUPERUSER=1

# 3. Cài đặt các package hệ thống cần thiết cho Laravel, Composer, Supervisor và healthcheck
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    supervisor \
    procps \
    default-mysql-client \
    netcat-openbsd \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    pkg-config \
    libbrotli-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 4. Cài đặt các extension PHP cốt lõi cho project Laravel
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    intl \
    zip \
    sockets

# 5. Cài đặt Redis, MongoDB và OpenSwoole để chạy Octane + realtime
RUN yes "" | pecl install redis mongodb openswoole \
    && docker-php-ext-enable redis mongodb openswoole

# 6. Cài đặt Composer từ image chính thức
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 7. Tạo user trong container để đồng bộ quyền với máy host
RUN useradd -G www-data,root -u ${uid} -d /home/${user} ${user} \
    && mkdir -p /home/${user}/.composer \
    && chown -R ${user}:${user} /home/${user}

# 8. Cấu hình thư mục làm việc đúng theo project thực tế của repo hiện tại
WORKDIR /var/www/html/BE_StayHub

# 9. Copy file composer trước để tận dụng layer cache khi build image
COPY ./BE_StayHub/composer.json ./
COPY ./BE_StayHub/composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# 10. Copy source backend để image vẫn tự chạy được khi không bind mount
COPY ./BE_StayHub ./
RUN chown -R ${user}:${user} /var/www/html/BE_StayHub

# 11. Chuẩn bị Supervisor và startup script để quản lý Octane + queue worker
RUN mkdir -p /var/log/supervisor
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start-container.sh /usr/local/bin/start-container.sh
RUN chmod +x /usr/local/bin/start-container.sh

# 12. Khởi động qua preflight script để đợi bind mount backend sẵn sàng sau khi mở máy
CMD ["/usr/local/bin/start-container.sh"]
