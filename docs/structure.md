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
│   │   │   ├── UserController.php        # 当前用户 / 更新资料
│   │   │   └── PhoneController.php       # 手机号绑定
│   │   ├── Requests/
│   │   │   ├── LoginRequest.php          # 登录参数校验
│   │   │   └── UpdateUserRequest.php     # 用户更新参数校验
│   │   └── Resources/
│   │       └── UserResource.php          # 用户 JSON 响应格式
│   ├── Models/
│   │   └── User.php                      # 用户模型（含 openid/phone/meta + tokens 关联）
│   ├── Services/
│   │   └── WechatService.php             # 微信 code2session + 手机号解密
│   ├── Filament/                          # 管理后台（FilamentPHP v5）
│   │   ├── Resources/
│   │   │   ├── UserResource.php          # 用户管理（列表/创建/编辑）
│   │   │   └── TokenResource.php         # API Token 管理（撤销）
│   │   ├── Pages/
│   │   │   └── Dashboard.php             # 自定义工作台首页（覆盖默认 Dashboard）
│   │   ├── Widgets/                      # 仪表盘组件
│   │   │   ├── UserStatsWidget.php       # 统计卡片
│   │   │   ├── UserRegistrationChart.php # 注册趋势图
│   │   │   ├── GenderDistributionChart.php # 性别分布图
│   │   │   └── RecentUsersTable.php     # 最近用户表
│   │   └── Providers/
│   │       └── AdminPanelProvider.php   # 后台面板配置
│   └── Providers/
│       ├── AppServiceProvider.php        # API 限流配置
│       └── Filament/
│           └── AdminPanelProvider.php   # 后台面板注册
├── bootstrap/
│   ├── app.php                           # 应用引导（路由/中间件/异常处理）
│   └── providers.php                     # 服务提供者注册
├── config/
│   ├── services.php                      # 微信小程序 app_id/secret 配置
│   ├── sanctum.php                       # Sanctum 配置（含 Token 过期）
│   ├── cors.php                          # 跨域配置（微信小程序调用 API）
│   └── filament.php                      # Filament 后台配置（发布后存在）
├── database/
│   └── migrations/
│       ├── 0001_..._create_users_table.php       # users（扩展字段）
│       ├── 2026_..._add_phone_to_users_table.php # 新增 phone 字段
│       └── 2026_..._create_personal_access_tokens_table.php
├── docs/                                 # 📘 本文档集
│   ├── README.md
│   ├── install.md
│   ├── config.md
│   ├── auth.md
│   ├── database.md
│   ├── structure.md
│   ├── testing.md
│   └── changelog.md
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
── tests/                                # 测试文件（见 testing.md）
│   ├── Feature/
│   │   ├── AuthTest.php                  # 登录/退出测试
│   │   ├── UserTest.php                  # 用户接口测试
│   │   └── PhoneTest.php                 # 手机号绑定测试
│   └── Unit/
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

### `app/Http/Controllers/PhoneController.php`
- `bind(Request)`：接收微信 `wx.getPhoneNumber()` 返回的 code → 调 `WechatService::getPhoneNumber()` → 绑定到当前用户。

### `app/Http/Requests/LoginRequest.php`
登录接口参数校验：`code` 必填、字符串、最大 128 字符。

### `app/Http/Requests/UpdateUserRequest.php`
用户更新接口参数校验：`nickname`、`avatar`、`gender`、`meta` 可选，各有对应规则。

### `app/Http/Resources/UserResource.php`
统一用户 JSON 响应格式，包含所有用户字段及时间戳。

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

---

## 6. 管理后台（FilamentPHP）

后台路径 `/admin`，由 `app/Providers/Filament/AdminPanelProvider.php` 定义：

- **自定义工作台首页**（`app/Filament/Pages/Dashboard.php`）：继承 `Filament\Pages\Dashboard`，覆盖默认的纯 widgets 首页。
  - 顶部欢迎区（带可折叠说明侧栏）+ 右上「查看全部用户」快捷入口。
  - 布局：统计卡片 → 2 列（注册趋势图 + 性别分布图）→ 最近注册用户表，均由 `content(Schema)` 用 `Grid`/`Section` 重组。
  - 导航标签为「工作台」，`navigationSort = -2` 固定在最前。
- **系统管理**：
  - `UserResource`：用户增删改查、性别/小程序用户/已绑定手机/注册时间筛选、手机号一键复制。
  - `TokenResource`：查看与撤销 Sanctum Token（`canCreate()` 关闭，因为 Token 由登录接口签发）。
- 访问控制：`User::canAccessPanel()` —— 仅拥有 `email` + `password` 的管理员可登录后台。
