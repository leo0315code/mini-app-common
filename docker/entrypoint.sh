#!/bin/sh
set -e

cd /var/www

# 首次启动：生成 .env（凭证仍通过 compose 环境变量注入）
if [ ! -f .env ]; then
    cp .env.example .env
fi

# 若未配置 APP_KEY 则生成
if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
fi

# 清理缓存并重新发现包（避免宿主机 vendor 产物干扰镜像内依赖）
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan package:discover --ansi

# 执行数据库迁移（幂等，--force 跳过交互确认）
php artisan migrate --force

# 确保存储目录可写
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
