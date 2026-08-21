# 安装与部署

## 1. 环境要求

| 组件 | 版本要求 | 说明 |
| --- | --- | --- |
| PHP | >= 8.3 | 需开启扩展：`ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml` |
| Composer | >= 2.5 | 包管理 |
| 数据库 | SQLite / MySQL 5.7+ / PostgreSQL 13+ | 开发默认 SQLite |
| Git | >= 2.x | 版本管理 |
| 微信小程序 | 已注册 AppID / AppSecret | 在微信公众平台「开发 → 开发设置」获取 |

> 本机示例环境：macOS + ServBay(PHP 8.3.30) + Composer 2.7。

---

## 2. 获取代码

```bash
# 方式一：克隆远程（远程已预先添加）
git clone git@github.com:leo0315code/mini-app-common.git
cd mini-app-common

# 方式二：本地已通过 composer create-project 创建
# 进入项目根目录即可，无需 clone
```

远程仓库已配置：

```bash
git remote -v
# origin  git@github.com:leo0315code/mini-app-common.git (fetch)
# origin  git@github.com:leo0315code/mini-app-common.git (push)
```

---

## 3. 安装依赖与初始化

```bash
# 安装 PHP 依赖
composer install

# 生成 .env（首次）
cp .env.example .env

# 生成应用密钥（必须）
php artisan key:generate

# 数据库迁移（默认 SQLite，已内置 database/database.sqlite）
php artisan migrate

# 可选：填充测试数据
php artisan db:seed
```

---

## 4. 配置微信小程序

编辑 `.env`，填入你的小程序凭证（见 [config.md](./config.md)）：

```env
MINI_PROGRAM_APP_ID=wx你的小程序AppID
MINI_PROGRAM_SECRET=你的小程序AppSecret
```

> ⚠️ `AppSecret` 属于敏感信息，**切勿提交到仓库**。`config/app.php`（或 `.env.example`）中只保留占位符。

---

## 5. 启动开发服务器

```bash
php artisan serve
# 默认监听 http://127.0.0.1:8000
```

小程序端请求基地址：`https://你的域名` 或开发期 `http://127.0.0.1:8000`。

---

## 6. Docker 部署

提供一键容器化启动（PHP-FPM + Nginx + MySQL + Redis）：

```bash
docker compose up -d --build
```

- 前台地址：http://localhost:8080 （后台 `/admin`）
- 首次启动自动生成 `.env` 与 `APP_KEY`，并执行数据库迁移
- 填充测试数据：`docker compose exec app php artisan db:seed`
- 查看状态 / 日志 / 停止：
  ```bash
  docker compose ps
  docker compose logs -f app
  docker compose down          # 加 -v 连同数据卷一并删除
  ```

### 相关文件

| 文件 | 说明 |
| --- | --- |
| `Dockerfile` | 多阶段构建：composer 依赖 → 前端 Vite 构建（Filament 主题）→ PHP 8.3-FPM 运行镜像（含 pdo_mysql / redis / intl / gd / zip 扩展） |
| `docker-compose.yml` | 4 服务编排：`app`（PHP-FPM）、`nginx`（80）、`mysql`（8.0）、`redis`（7），含健康检查与数据卷 |
| `docker/nginx/default.conf` | Nginx 站点配置（`public/` 根目录、PHP-FPM 转发、静态资源回退 Laravel） |
| `docker/entrypoint.sh` | 首次启动初始化：生成 `.env` / `APP_KEY`、清理缓存、`migrate --force` |

### 自定义

- **端口**：`.env` 设置 `APP_PORT`（默认 8080）
- **数据库**：容器内自动覆盖为 `DB_HOST=mysql`；库名 / 用户 / 密码用 `MYSQL_DATABASE` / `MYSQL_USER` / `MYSQL_PASSWORD` 配置（默认 `mini_app_common` / `mini_app` / `mini_app_secret`，与宿主机 `.env` 的 `DB_*` 隔离）
- **缓存 / 会话 / 队列**：自动使用 Redis（`CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION=redis`）
- **微信凭证**：在宿主机 `.env` 填入 `MINI_PROGRAM_APP_ID` / `MINI_PROGRAM_SECRET`，compose 自动注入容器

---

## 7. Docker 生产部署

在服务器上使用容器运行本项目（PHP-FPM + Nginx + MySQL + Redis）。

### 7.1 服务器准备

- 安装 Docker Engine 与 Compose 插件：
  ```bash
  curl -fsSL https://get.docker.com | sh
  docker compose version
  ```
- 克隆代码：`git clone git@github.com:leo0315code/mini-app-common.git && cd mini-app-common`

### 7.2 生产环境配置

```bash
cp .env.example .env
```

关键变量：

| 变量 | 生产建议值 |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://你的域名` |
| `APP_PORT` | `8080`（默认，供 Nginx 反代或直接访问） |
| `MINI_PROGRAM_APP_ID` / `MINI_PROGRAM_SECRET` | 微信小程序凭证 |
| `MYSQL_DATABASE` / `MYSQL_USER` / `MYSQL_PASSWORD` / `MYSQL_ROOT_PASSWORD` | 使用强密码，勿用默认值 |
| `SANCTUM_TOKEN_EXPIRATION` | 按需设置 Token 有效期（分钟，空=永久） |

> 生产环境密码请务必修改 `docker-compose.yml` 中的 `MYSQL_*` 默认值；微信小程序后台配置「request 合法域名」为 `https://你的域名`。

### 7.3 构建与启动

```bash
docker compose up -d --build
docker compose ps                # 确认 4 个服务 healthy
curl http://localhost:8080/up    # 应返回 200
```

- 首次启动自动生成 `.env` / `APP_KEY` 并执行迁移
- 创建管理员：`docker compose exec app php artisan db:seed`
- 生产环境应手动收紧权限：`docker compose exec app chmod -R ug+rw storage`

### 7.4 HTTPS

小程序要求合法域名必须是 HTTPS，二选一：

- **方式一：Nginx 容器直挂证书**：将证书挂载进容器，在 `docker/nginx/default.conf` 增加 443 `server` 块并 `docker compose restart nginx`
- **方式二（推荐）：外部反代**：用 Caddy / Nginx / 云负载均衡将 `https://你的域名` 反向代理到服务器 `:8080`，应用层无需改动

### 7.5 更新发布

```bash
git pull
docker compose build            # 代码 / 依赖 / 前端资源有变更时重建
docker compose up -d            # 启动新容器
docker compose exec app php artisan migrate --force   # 执行新迁移
```

> `docker compose up -d --build` 可一步完成「构建 + 启动」。修改宿主机 `.env` 后需 `docker compose up -d` 重启容器才生效。

### 7.6 数据备份与恢复

```bash
# 备份 MySQL
docker compose exec mysql sh -c "mysqldump -u\$MYSQL_USER -p\$MYSQL_PASSWORD \$MYSQL_DATABASE" > backup.sql

# 恢复 MySQL
cat backup.sql | docker compose exec -T mysql sh -c "mysql -u\$MYSQL_USER -p\$MYSQL_PASSWORD \$MYSQL_DATABASE"
```

### 7.7 数据持久化说明

- 数据保存在命名卷 `mysql_data` / `redis_data`：`docker compose down` 不丢数据，`docker compose down -v` 会连同数据一并删除
- Redis 仅缓存 / 会话 / 队列，可随时重建；MySQL 是唯一需要定期备份的数据源

### 7.8 常见问题

- **`livewire.js` 404**：静态资源请求需回退到 Laravel（`docker/nginx/default.conf` 已内置 `try_files $uri /index.php`），无需额外处理
- **修改代码不生效**：源码挂载在开发环境生效；生产镜像需 `docker compose build` 重建
- **后台无样式**：确认镜像包含前端构建产物，执行 `docker compose build` 重建
- **Redis 连接失败**：确认 `REDIS_CLIENT=phpredis`（镜像已装扩展）且宿主机 `.env` 未覆盖 `REDIS_HOST`

---

## 8. 生产部署要点

1. **关闭调试**：`.env` 中 `APP_ENV=production`、`APP_DEBUG=false`。
2. **使用正式数据库**：将 `DB_CONNECTION` 改为 `mysql` 并填好连接信息。
3. **微信回调域名**：在小程序后台配置「request 合法域名」为你的后端域名（必须 HTTPS）。
4. **队列与缓存**：生产建议配置 Redis 缓存（`CACHE_DRIVER=redis`）。
5. **Web 服务器**：生产用 Nginx + PHP-FPM，文档根目录指向 `public/`，并配置伪静态（Laravel 已内置 `.htaccess`，Nginx 需 `try_files $uri /index.php?$query_string;`）。

---

## 9. Git 与版本号规范

### 分支策略

| 分支 | 用途 |
| --- | --- |
| `main` | 稳定主干，可部署 |
| `dev` | 开发集成（可选） |
| `feature/xxx` | 功能开发 |
| `fix/xxx` | 缺陷修复 |

### 提交信息（Conventional Commits）

```
<type>(<scope>): <subject>

type: feat | fix | docs | refactor | chore | test | style
示例:
  feat(auth): 新增微信小程序 code2session 登录
  docs: 补充安装文档
  fix(user): 修复 openid 唯一约束冲突
```

### 版本发布（SemVer + Tag）

```bash
# 在 main 分支完成功能后，打版本标签
git tag -a v1.0.0 -m "v1.0.0 通用微信小程序后台骨架"
git push origin v1.0.0
```

版本号规则：
- **主版本** `MAJOR`：不兼容的 API 变更
- **次版本** `MINOR`：向后兼容的新功能
- **修订号** `PATCH`：向后兼容的缺陷修复

首个交付版本：**`v1.0.0`**。
