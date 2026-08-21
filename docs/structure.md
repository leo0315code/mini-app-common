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
│   │   ├── User.php                      # 用户模型（含 openid/phone/meta + tokens 关联）
│   │   ├── Announcement.php              # 公告（小程序端拉取已发布内容）
│   │   ├── Feedback.php                  # 用户反馈（小程序端提交，后台处理）
│   │   ├── AuditLog.php                  # 操作审计日志
│   │   ├── Category.php                  # 内容分类（CMS）
│   │   └── Article.php                   # 文章/内容（CMS，小程序端按频道拉取）
│   ├── Services/
│   │   └── WechatService.php             # 微信 code2session + 手机号解密
│   │   └── Audit.php                     # 审计日志便捷写入服务
│   ├── Observers/
│   │   └── AuditObserver.php             # 监听 User/Announcement/Feedback 写库自动记审计
│   ├── Http/
│   │   └── Controllers/
│   │       ├── AuthController.php        # 微信登录 / 退出
│   │       ├── UserController.php        # 当前用户 / 更新资料
│   │       ├── PhoneController.php       # 手机号绑定
│   │       ├── AnnouncementController.php # 小程序端公告列表/详情（公开）
│   │       └── FeedbackController.php    # 小程序端反馈提交（auth:sanctum）
│   ├── Filament/                          # 管理后台（FilamentPHP v5）
│   │   ├── Resources/
│   │   │   ├── UserResource.php          # 用户管理（列表/创建/编辑 + 角色指派）
│   │   │   ├── TokenResource.php         # API Token 管理（撤销）
│   │   │   ├── RoleResource.php          # 角色管理（RBAC）
│   │   │   ├── AnnouncementResource.php  # 公告管理（增删改查 + 发布状态）
│   │   │   ├── FeedbackResource.php      # 用户反馈（查看 + 处理动作）
│   │   │   ├── NotificationResource.php  # 站内通知（群发/定向/已读回执）
│   │   │   ├── MediaResource.php         # 媒体文件管理（上传/列表/删除）
│   │   │   ├── CategoryResource.php      # 内容分类管理（CMS）
│   │   │   ├── ArticleResource.php       # 文章管理（CMS，封面/摘要/置顶）
│   │   │   └── AuditLogResource.php      # 操作日志（只读 + 详情）
│   │   ├── Pages/
│   │   │   ├── Dashboard.php             # 自定义工作台首页（覆盖默认 Dashboard）
│   │   │   └── SystemConfig.php          # 系统配置（写 settings 表 + 运行时同步）
│   │   ├── Widgets/                      # 仪表盘组件
│   │   │   ├── UserStatsWidget.php       # 统计卡片
│   │   │   ├── UserRegistrationChart.php # 注册趋势图
│   │   │   ├── GenderDistributionChart.php # 性别分布图
│   │   │   └── RecentUsersTable.php     # 最近用户表
│   │   └── Providers/
│   │       └── AdminPanelProvider.php   # 后台面板配置
│   └── Models/
│       ├── Role.php                     # 角色（RBAC）
│       ├── Notification.php             # 站内通知 + 派发回执
│       └── Media.php                    # 媒体文件记录
│   └── Providers/
│       ├── AppServiceProvider.php        # API 限流 + 审计 Observer 注册
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
├── docker/                               # Docker 部署
│   ├── nginx/default.conf                # Nginx 站点配置
│   └── entrypoint.sh                     # 首次启动初始化 + 迁移
├── Dockerfile                            # 多阶段构建（composer + 前端 + PHP-FPM）
├── docker-compose.yml                    # app / nginx / mysql / redis 编排
└── .dockerignore
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
  - `UserResource`：用户增删改查、性别/小程序用户/已绑定手机/注册时间筛选、手机号一键复制；表单可指派「后台角色」（CheckboxList，RBAC），表格显示角色徽标。
  - `RoleResource`（导航「角色管理」，分组「系统管理」）：后台角色维护（名称/slug/说明），列表显示成员数。默认 4 个角色：`super-admin`(超级管理员) / `admin`(管理员) / `editor`(编辑) / `viewer`(访客)。
  - `MediaResource`（导航「媒体管理」，分组「系统管理」）：管理上传的图片/文件，FileUpload 接本地 public disk（`uploads/` 目录），按分组筛选，删除即删实体文件。
  - `TokenResource`：查看与撤销 Sanctum Token（`canCreate()` 关闭，因为 Token 由登录接口签发）。
  - `SystemConfig`（导航「系统配置」，`app/Filament/Pages/SystemConfig.php`）：集中维护可后台变更的系统参数，落库到 `settings` 表并按分组读取。涵盖：
    - 微信小程序 `app_id` / `secret`（默认回退 `config('services.mini_program')`）。
    - 跨域 `CORS_ALLOWED_ORIGINS` / `CORS_MAX_AGE`（逗号分隔来源，运行时 explode 写入 `config('cors')`）。
    - 安全：`SANCTUM_TOKEN_EXPIRATION`（填 0/空=永久）、`SANCTUM_TOKEN_PREFIX`。
    - 站点：后台品牌名 `'brandName'`。
    - 保存时既写入 `settings` 表（持久化），又同步到当前请求运行时 `config`；未配置项回退各 `config` 文件默认值。
  - `AuditLogResource`（导航「操作日志」，分组「系统管理」）：只读审计列表，记录 `type`(create/update/delete/login/config) + `module` + 操作人/时间/IP/变更 diff。提供列表筛选（类型/模块/操作人/时间）与详情查看（`view` 页）。导航徽标显示当日日志数。
- **内容运营**（导航分组）：
  - `AnnouncementResource`：公告管理（增删改查）。字段：标题/正文(RichEditor)/类型(通知·活动·版本更新)/状态(草稿·已发布·已下线)/发布时间；列表按状态、类型筛选，发布后立即生效；小程序端经 `/api/announcements` 拉取已发布内容。
  - `CategoryResource`（导航「内容分类」，分组「内容运营」，sort 5）：内容频道栏目维护（名称/slug/说明/排序/启用），列表显示文章数关联统计；支持启用过滤。
  - `ArticleResource`（导航「文章管理」，分组「内容运营」，sort 6）：完整内容撰写与发布。字段：分类(下拉可内联创建)/标题/slug/封面(FileUpload 接 `articles/` 目录)/摘要/正文(RichEditor)/状态(草稿·已发布·已下线)/置顶/发布时间；列表按分类/状态/置顶筛选，显示封面缩略图、状态徽标、浏览数；创建时记录作者，发布无发布时间则补当前时间；小程序端经 `GET /api/article-categories`（启用栏目）、`GET /api/articles`（`category_id`/`keyword` 过滤、置顶优先、公开已发布）、`GET /api/articles/{id}`（详情且浏览数自增）交互。
  - `NotificationResource`（导航「站内通知」）：广播消息 + 已读回执。支持接收范围（全部 / 已注册小程序用户 / 指定用户），保存后按 scope 展开收件人写入 `notification_user`；列表显示接收人数、发送人、已发布状态；导航徽标显示通知总量；小程序端经 `GET /api/notifications`（含未读数）、`POST /api/notifications/{id}/read`、`POST /api/notifications/read-all` 交互。
  - `FeedbackResource`：用户反馈（只读 + 处理）。列表按类型/状态筛选，导航徽标显示待处理数；每条提供「处理」动作（改状态 pending/processing/resolved/rejected + 处理备注），处理人/处理时间自动记录；小程序端经 `POST /api/feedback` 提交（auth:sanctum）。
- 访问控制：`User::canAccessPanel()` 增强为「拥有 `email` + `password` **且**具备 `admin`/`super-admin` 角色」；首次部署（`roles` 表为空）时回退旧放行规则避免锁死。普通小程序用户（无 email/password）无法进入后台。
- 审计自动写入：`App\Observers\AuditObserver` 在 `AppServiceProvider::boot()` 注册，监听 `User`/`Announcement`/`Feedback` 的 Eloquent `created`/`updated`/`deleted` 事件，自动调用 `App\Services\Audit::log()` 写 `audit_logs` 表。

### `app/Models/Setting.php` 与 `settings` 表
- 键值表：`group` + `key` 唯一，单列 `value` 为 JSON（`array` cast）。
- `Setting::getGroup(string $group, array $defaults)`：读取某分组全部配置，返回 `key => value` 数组，并与 `$defaults` 合并。
- `Setting::setGroup(string $group, array $data)`：按分组 upsert 配置项（`updateOrCreate`）。
- 迁移：`database/migrations/2026_08_21_160000_create_settings_table.php`。
