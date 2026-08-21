# 目录结构与核心文件

本文档说明 `mini-app-common` 的关键目录与文件职责，便于二次开发与维护。

---

## 1. 目录树（核心）

```
mini-app-common/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php        # 微信登录 / 退出
│   │   │   └── UserController.php        # 当前用户
│   │   └── ...                           # Laravel 默认 Kernel / Middleware
│   ├── Models/
│   │   └── User.php                      # 用户模型（含 openid/meta）
│   └── Services/
│       └── WechatService.php             # 微信 code2session 封装
├── bootstrap/
│   ├── app.php                           # 应用引导（路由/中间件/异常处理）
│   └── providers.php                     # 服务提供者注册
├── config/
│   ├── services.php                      # 微信小程序 app_id/secret 配置
│   └── sanctum.php                       # Sanctum 配置
├── database/
│   └── migrations/
│       ├── 0001_..._create_users_table.php       # users（扩展字段）
│       └── 2026_..._create_personal_access_tokens_table.php
├── docs/                                 # 📘 本文档集
│   ├── README.md
│   ├── install.md
│   ├── config.md
│   ├── auth.md
│   ├── database.md
│   └── structure.md
├── public/
│   ├── index.php                         # 入口
│   ── .htaccess                         # Apache 伪静态规则
├── routes/
│   ├── api.php                           # API 路由（本项目新增）
│   ├── web.php                           # Web 路由（默认欢迎页）
│   └── console.php                       # Artisan 命令路由
├── resources/
│   ├── css/app.css                       # Tailwind CSS 入口
│   ├── js/app.js                         # 前端 JS 入口
│   └── views/welcome.blade.php           # 默认欢迎页
├── storage/                              # 日志、缓存、上传文件
├── tests/                                # 测试文件（见 testing.md）
├── .env / .env.example                   # 环境变量
├── vite.config.js                        # Vite 前端构建配置
└── composer.json
```

---

## 2. 核心文件职责

### `app/Services/WechatService.php`
封装对微信 `jscode2session` 的调用，返回 `openid` / `unionid` / `session_key`。
- 失败抛出异常，由 Controller 统一转 401。
- 密钥从 `config('services.mini_program')` 读取，不硬编码。

### `app/Http/Controllers/AuthController.php`
- `login(Request)`：接收 `code` → 调 `WechatService` → 按 `openid` `firstOrCreate` → 签发 Sanctum Token。
- `logout(Request)`：吊销当前 Token。

### `app/Http/Controllers/UserController.php`
- `show(Request)`：返回 `$request->user()`（需 `auth:sanctum`）。

### `app/Models/User.php`
- 启用 `HasApiTokens` / 配置 `openid` 等可填字段 / `meta` 为 JSON 类型。

### `bootstrap/app.php`
应用引导文件，Laravel 13 采用链式配置风格：

- **`withRouting()`**：注册路由文件
  - `web` → `routes/web.php`（Web 路由）
  - `api` → `routes/api.php`（API 路由，自动加 `/api` 前缀）
  - `commands` → `routes/console.php`（Artisan 命令）
  - `health` → `/up`（健康检查端点）
- **`withMiddleware()`**：注册全局中间件
  - API 路由前置 `ThrottleRequests:api` 限流
- **`withExceptions()`**：异常处理
  - API 请求（`api/*`）或期望 JSON 的请求，异常以 JSON 格式返回

### `bootstrap/providers.php`
注册应用的服务提供者（Service Providers），Laravel 13 默认包含 `AppServiceProvider`。

### `config/services.php`（mini_program 段）
```php
'mini_program' => [
    'app_id'  => env('MINI_PROGRAM_APP_ID'),
    'secret' => env('MINI_PROGRAM_SECRET'),
],
```

---

## 3. 请求生命周期

```
小程序请求
  → Nginx/artisan serve → public/index.php
  → Laravel 路由（routes/api.php）
  → 中间件（throttle:api / auth:sanctum）
  → Controller → Service/Model
  → JSON 响应
```

---

## 4. 扩展指引

- **新增业务接口**：在 `routes/api.php` 以 `/api` 前缀、`auth:sanctum` 保护下注册；Controller 放 `app/Http/Controllers/`。
- **换小程序**：仅改 `.env` 的 `MINI_PROGRAM_APP_ID` / `SECRET`，无需改动代码。
- **多小程序共用**：可在 `users` 增加 `app_id` 列并在 `openid` 上做联合唯一；v1.0.0 默认单小程序单库部署。
- **加业务表**：`php artisan make:migration` 新建迁移，遵循 `database.md` 命名规范。

---

## 5. 命名约定

- 路由：`kebab-case`，统一 `/api` 前缀。
- 控制器：`<Xxx>Controller`，方法 `index/show/store/update/destroy/login/logout`。
- 服务类：`<Xxx>Service`，动词方法（`code2Session`、`getUserInfo`）。
- 配置键：`UPPER_SNAKE_CASE`，分组在 `config/services.php`。
