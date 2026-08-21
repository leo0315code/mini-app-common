# ---- PHP 依赖阶段（为前端构建提供 vendor 资源） ----
FROM composer:2 AS deps

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --ignore-platform-reqs --no-scripts --no-autoloader

# ---- 前端构建阶段（Filament 后台主题资源） ----
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY . .
COPY --from=deps /app/vendor ./vendor
RUN npm run build

# ---- 运行时阶段 ----
FROM php:8.3-fpm

# 安装系统依赖（编译扩展所需 + 常用工具）
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libgd-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# 安装 PHP 扩展
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 复制项目源码（vendor 等已在 .dockerignore 排除）
COPY . .

# 复制前端构建产物（public/build）
COPY --from=frontend /app/public/build ./public/build

# 安装生产依赖
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

# 目录权限
RUN chown -R www-data:www-data storage bootstrap/cache

# 入口脚本（首次启动初始化 + 迁移）
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
