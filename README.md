# 微信小程序通用后台（mini-app-common）

> 基于 **Laravel 13 + PHP 8.3** 的「一套后台，接入任意微信小程序」通用后端解决方案。

通过配置 `mini_program.app_id / secret` 即可接入一个小程序，无需改代码；内置 `code2session` 登录、Sanctum Bearer Token 鉴权、可扩展用户体系，并配套 Filament 管理后台。

## 核心能力

- **微信登录**：`POST /api/auth/login`，`code2session` 换取 `openid` / `unionid`，签发 Sanctum Token
- **用户体系**：`GET/PUT /api/user` 查询与更新资料（昵称、头像、性别、meta 扩展字段）
- **手机号绑定**：`POST /api/user/phone`，微信新版接口换取并绑定手机号
- **统一 API 格式**：`{"code": 0, "message": "...", "data": {...}}`，全局异常统一处理
- **CORS 跨域**：`config/cors.php` + 环境变量，适配小程序 `wx.request`
- **管理后台**：FilamentPHP v5，`/admin` 提供用户管理、Token 撤销、数据统计仪表盘、系统配置（品牌「宏图爱」）

详细文档见 [`docs/`](./docs/) 目录：

| 文档 | 内容 |
| --- | --- |
| [docs/README.md](./docs/README.md) | 项目总览、快速开始、技术栈 |
| [docs/install.md](./docs/install.md) | 环境要求、安装、部署、Git / 版本规范 |
| [docs/config.md](./docs/config.md) | 微信小程序、数据库、鉴权、CORS 配置 |
| [docs/auth.md](./docs/auth.md) | 微信登录流程图 + 完整 API 接口文档 |
| [docs/database.md](./docs/database.md) | 数据表结构设计 |
| [docs/structure.md](./docs/structure.md) | 目录结构与核心文件职责 |
| [docs/testing.md](./docs/testing.md) | 测试指南 |
| [docs/admin.md](./docs/admin.md) | 管理后台使用指南（FilamentPHP） |
| [docs/changelog.md](./docs/changelog.md) | 版本变更记录 |

## 快速开始

### 本地开发

```bash
composer install
cp .env.example .env && php artisan key:generate
# 编辑 .env 设置 MINI_PROGRAM_APP_ID / MINI_PROGRAM_SECRET
php artisan migrate
php artisan db:seed        # 可选：填充管理员 + 测试数据
php artisan serve         # http://localhost:8000 ，后台 /admin
```

### Docker 一键启动

```bash
docker compose up -d --build
# 前台 http://localhost:8080 ，后台 http://localhost:8080/admin
# 首次启动自动迁移；填充数据：docker compose exec app php artisan db:seed
```

运行测试：

```bash
php artisan test          # 26 个用例，覆盖登录 / 用户 / 手机号 / 系统配置
```

## 技术栈

- Laravel 13 + PHP 8.3+
- Laravel Sanctum 4.x（API Token 鉴权）
- 默认 SQLite（开发），支持 MySQL / PostgreSQL（生产）
- FilamentPHP v5（管理后台）
- Vite + Tailwind CSS v4（后台资源）

## 版本规范

遵循语义化版本（SemVer），发布时打 `git tag`（如 `v1.0.0`）。详见 [`docs/changelog.md`](./docs/changelog.md)。
