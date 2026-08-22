# CHANGELOG

本项目遵循 [语义化版本（SemVer）](https://semver.org/lang/zh-CN/) 规范。

---

## [v1.9.0] - 2026-08-22

用户封禁（`roadmap` P1 第 2 项，改动涉及表结构）：

- **数据表**：`users` 新增 `status`（normal/banned）、`banned_at`、`ban_reason` 字段及 `status` 索引。
- **模型**：`User` 增加 `isBanned()` / `ban($reason)` / `unban()` / `scopeActive()`；`ban()` 同时撤销该用户全部 API Token（立即踢下线）。
- **接口拦截**：
  - 新增中间件 `EnsureUserNotBanned`，挂在 `api` 路由组的 `auth:sanctum` 之后，封禁用户即使持旧 Token 访问受保护接口也返回 `40301 账号已被封禁`。
  - 登录（`POST /api/auth/login`）校验封禁状态，封禁用户拒绝登录并吊销已有登录态（返回 `40301`）。
- **后台用户管理**：列表新增「状态」徽标列（正常/已封禁）、按状态筛选；行内新增「封禁 / 解封」动作（带确认，解封仅对封禁用户显示）。
- **测试**：新增 `tests/Feature/UserBanTest.php`（登录拦截、接口中间件拦截、解封恢复、后台 ban/unban 底层逻辑、列表页动作渲染），全量 **68 passed (181 assertions)**。
- **注意**：部署需执行 `php artisan migrate`；本地主库（laravel-mini）当前离线，上线时务必先起 MySQL 再迁移。

---

## [v1.8.0] - 2026-08-21

后台体验打磨（纯 UI/交互，不改表结构）：

- **用户反馈**：列表页新增「批量已解决」toolbar 动作（带确认）；反馈详情/处理表单新增「处理人 / 处理时间」只读区块；处理表单默认状态改为「处理中」；待处理行在列表高亮（amber 背景）；列表可展示「处理时间」列。
- **站内通知**：列表页顶栏新增「全部标记已读」动作（仅更新当前管理员自身回执，不影响其他接收人）；列表新增「已读率」列（badge，100% 绿色）；未发布通知在列表行淡化显示（用户端不可见）。
- **媒体管理**：列表新增「链接」列（图片可点击新窗口预览、所有条目支持一键复制 URL）；新增「类型」筛选（图片/文档）；上传时按扩展名自动归类分组（images/documents/others），不再统一落 `uploads`。
- **视觉**：`resources/css/filament/admin/theme.css` 追加待处理行高亮与未发布通知行淡化样式。
- **测试**：新增 `tests/Feature/AdminUxTest.php`（反馈处理/批量已解决、通知批量已读、媒体扩展名归类、各列表/详情页渲染），全量 **62 passed (162 assertions)**。

---

## [v1.7.0] - 2026-08-21

- **内容管理 CMS（文章系统）**：新增 `categories` + `articles` 表与 `Category` / `Article` 模型。
  - 分类 `Category`：名称/slug/说明/排序/启用，支持 `active()` / `ordered()` 作用域；后台 `CategoryResource` 增删改查（含 `articles_count` 关联统计）。
  - 文章 `Article`：标题/slug/封面/摘要/正文(RichEditor)/状态(草稿·已发布·已下线)/置顶/浏览数/作者/发布时间；支持按分类筛选、置顶优先排序、浏览数自增；后台 `ArticleResource` 表单含分类下拉（可内联创建）、封面图上传（`articles/` 目录）、状态与置顶；列表含封面、状态徽标、浏览数。
  - 与 v1.5.0「公告」分层：公告用于轻量速讯/通知，文章用于完整内容频道（支持分类与封面）。
- **内容中心 API（公开）**：小程序端 `GET /api/article-categories`（启用的频道）、`GET /api/articles`（已发布列表，支持 `category_id` / `keyword` 过滤、置顶优先排序）、`GET /api/articles/{id}`（详情，自增浏览数；草稿返回 404）。统一 `code/message/data` 格式。
- **审计增强**：`AuditObserver` 增加 `Article` / `Category` 监听，`module` 分别记 `article` / `category`，创建/修改/删除自动落 `audit_logs`。
- **种子数据**：`DatabaseSeeder` 播种「帮助中心 / 平台公告 / 活动专区」三个分类与若干示例文章（含一篇置顶）。
- **测试**：新增 `CmsTest`（分类公开列表/文章公开列表与分类过滤/详情浏览数自增/草稿隐藏/后台页面可访问/创建审计），全量 **50 passed**。`php -l` 全过；6 个受影响 Resource 语法校验通过。

### 修复：Filament v5 布局组件 Section 命名空间

- 初版 `ArticleResource`（及 `Category`/`Announcement`/`Feedback`/`Notification`/`Media` 共 6 个 Resource）误以 `use Filament\Forms\Components\Section;` 导入——Filament v5 中 `Section`/`Grid` 等**布局组件**属于 `Filament\Schemas\Components`，输入组件（Select/TextInput 等）才在 `Filament\Forms\Components`。列表页不构建 form 故此坑未被测试暴露，访问 create/edit 即报 `Class "Filament\Forms\Components\Section" not found`（GET /admin/articles/3/edit 500）。已全部改为 `use Filament\Schemas\Components\Section;`，并补 `test_admin_can_edit_article` / `test_admin_can_edit_category` 复现路径。

### 修复：列表页「新增/编辑」按钮缺失

- **「新增」按钮**：Filament v5 列表页顶部 CreateAction 需由 List 页面 `getHeaderActions()` 显式返回 `Actions\CreateAction::make()`（仅 `Notification`/`Media`/`Role`/`User` 当初显式加了，`Article`/`Category`/`Announcement` 漏加，导致列表页顶部无「新增」入口）。已为这 3 个 List 页面补 `getHeaderActions()`。
- **「编辑」按钮（Media）**：`MediaResource` 在 v1.6.0 只创建了 `ListMedia`/`CreateMedia`，**漏建 `EditMedia` 页面且 `getPages()` 无 `edit` 路由**，导致行内 `EditAction` 无法生成链接（列表页无「编辑」入口）。已补建 `EditMedia.php`（继承 `EditRecord`，含 DeleteAction）并在 `getPages()` 注册 `edit`。同时给 `MediaResource` 的 `recordActions` 补 `EditAction::make()`。
- 验证：新增 `tests/Feature/ListActionsProbeTest.php`，逐一断言各业务资源列表页含「新增」href 与「编辑」href，全量 **55 passed**。只读资源（审计/反馈/Token）与单页（系统配置）本就不该有增删改，属设计预期。

---

## [v1.6.0] - 2026-08-21

- **RBAC 角色与权限**：新增 `roles` + `role_user` 表与 `Role` 模型；`User` 增加 `roles()` 关联、`hasRole()`/`isSuperAdmin()`/`assignRole()`；`canAccessPanel` 增强为「email+password 且拥有 admin/super-admin 角色」，并在首次部署（roles 空）时回退旧放行规则避免锁死；`RoleResource` 后台角色管理；`UserResource` 表单可指派角色、表格显示角色徽标；`DatabaseSeeder` 播种 4 个默认角色并将管理员设为 super-admin。
- **站内通知/消息**：`notifications` + `notification_user`（已读回执）表 + `Notification` 模型（`dispatchToRecipients()` 按 scope 展开收件人）；`NotificationResource` 后台群发/定向/列表，保存后自动派发；小程序端 `GET /api/notifications`、`POST /api/notifications/{id}/read`、`POST /api/notifications/read-all`（含未读数）。
- **文件/媒体管理**：`media` 表 + `Media` 模型（软删文件）；`MediaResource` 后台管理器（FileUpload 接 local public disk、分组筛选、删除即删文件）；小程序端 `POST /api/upload` 返回可访问 URL（auth:sanctum，10MB 限制）。
- **测试**：新增 RoleFactory / NotificationFactory / MediaFactory + `AdminModulesTest`（角色指派/canAccessPanel 规则/通知群发与指定/已读/媒体上传与校验），全量 **40 passed (111 assertions)**。
- **文档**：structure.md / database.md / changelog.md 同步。

> 注：RBAC 为轻量实现（角色 slug 控制后台访问与可见性），未引入 permission 包；资源级细粒度授权可作为后续扩展。

---

## [v1.5.0] - 2026-08-21

### 新增

- **操作审计（系统管理）**：新增 `audit_logs` 表 + `App\Models\AuditLog`、`App\Services\Audit`、`App\Observers\AuditObserver`。Observer 监听 `User`/`Announcement`/`Feedback` 的 `created`/`updated`/`deleted` 事件，自动记录 `type`/`module`/操作人/时间/IP/变更 diff。`AuditLogResource`（只读 + 详情查看，导航徽标显示当日数）。
- **公告管理（内容运营）**：新增 `announcements` 表 + `Announcement` 模型与 `AnnouncementResource`（增删改查）。支持类型（通知/活动/版本更新）、状态（草稿/已发布/已下线）、发布时间；列表按类型/状态筛选；保存时自动记审计。`POST /api/announcements` + `GET /api/announcements/{id}` 供小程序端拉取已发布内容。
- **用户反馈（内容运营）**：新增 `feedback` 表 + `Feedback` 模型与 `FeedbackResource`（只读列表 + 详情 + 「处理」动作，导航徽标显示待处理数）。后台处理改状态并记录处理人/时间；`POST /api/feedback`（auth:sanctum）供小程序端提交。
- 新增 `database/factories/AnnouncementFactory.php`、`FeedbackFactory.php`（含 draft/offline 状态）。
- 新增 `tests/Feature/ContentApiTest.php`（公开公告列表/详情、反馈提交鉴权与校验、提交落库与审计）覆盖。

### 文档

- `structure.md`：目录树与核心文件职责补充 公告/反馈/审计/API 控制器；新增「内容运营」「审计自动写入」说明。
- `database.md`：业务表清单补充 settings/audit_logs/announcements/feedback，新增第 6 节 v1.5.0 表结构。
- `changelog.md`：新增 v1.5.0。

### 测试

- 全量测试 **33 passed (84 assertions)**。

---

## [v1.3.1] - 2026-08-21

### 新增

- **自定义后台工作台首页**：新增 `app/Filament/Pages/Dashboard.php`，继承 `Filament\Pages\Dashboard` 覆盖默认的纯 widgets 首页。新增欢迎区（可折叠说明侧栏）+ 右上「查看全部用户」快捷入口；页面布局由 `content(Schema)` 用 `Section` / `Grid` 重组为「统计卡片 → 2 列图表 → 最近用户表」；导航标签改为「工作台」并固定在最前。
- **工作台时间范围筛选（图表联动）**：Dashboard 引入 `HasFiltersForm` + `FilterAction`（右上角「筛选」弹窗），提供「近 7/30/90 天 / 全部」选项，URL 同步并 session 持久化。四个 widget 通过 `$this->pageFilters['range']` 联动：统计卡片改为显示该范围新增与手机绑定率；注册趋势图天数随范围变化；性别分布图与最近用户表按时间段过滤。统一由 `Dashboard::rangeDates()` 计算起止时间。

### 文档

- 更新 `structure.md`：目录树补充 `Pages/Dashboard.php`，第 6 节补充自定义工作台首页与时间范围筛选说明。

### 修复

- 修复 `User.php` 重复 `use Laravel\Sanctum\PersonalAccessToken` 导致的致命错误（Fatal）。
- 消除 Filament v5 弃用告警：`UserResource` / `TokenResource` 的 `->actions()` 改为 `->recordActions()`，`->bulkActions()` 改为 `->toolbarActions()`。
- 修复 `UnorderedList` 调用不存在的 `->compact()` 导致的 `/admin` 500（BadMethodCallException）。
- 为 `RecentUsersTable` 设置中文标题，消除自动生成的英文 "Recent Users Table"。

---

## [v1.4.0] - 2026-08-21

### 新增

- **后台「系统配置」页**：新增 `app/Filament/Pages/SystemConfig.php`，集中维护可后台变更的系统参数（微信小程序 app_id/secret、CORS 来源与预检缓存、Sanctum Token 有效期与前缀、后台品牌名）。采用 Filament v5 页面表单模式（`defaultForm` / `form` / `getFormActions` / `save` / `content` + `Form::make(EmbeddedSchema)`）。
- **`settings` 键值表与 `Setting` 模型**：新增迁移与 `App\Models\Setting`，提供 `getGroup()`（带默认值合并）/ `setGroup()`（按分组 upsert），`value` 为 JSON（`array` cast）。
- 保存配置时既持久化到 `settings` 表，又同步到当前请求运行时 `config`（CORS / Token / 品牌名即时生效；未配置项回退各 `config` 文件默认值）。
- 新增 `tests/Feature/SystemConfigTest.php`（鉴权跳转、管理员可访问、保存写库）覆盖。
- **「系统配置」归入「系统管理」导航分组**：与「用户管理」「Token 管理」并列，组内排序第 3。
- **Docker 容器化部署**：新增 `Dockerfile`（多阶段：composer 依赖 → 前端 Vite 构建 → PHP 8.3-FPM，含 pdo_mysql / redis / intl / gd / zip 扩展）、`docker-compose.yml`（app / nginx / mysql / redis 四服务，健康检查 + 数据卷）、`docker/nginx/default.conf`、`docker/entrypoint.sh`（首次启动初始化并迁移）、`.dockerignore`。
- **品牌与主题美化**：后台品牌名改为「宏图爱」，主色由 Amber 调整为 Emerald（`AdminPanelProvider`）。
- **默认管理员邮箱**：`DatabaseSeeder` 默认管理员改为 `453507012@qq.com`（密码 `admin123`），`docs/admin.md` 同步。
- **AGENTS.md**：新增供 OpenCode 会话参考的仓库约定与命令速查。

### 文档

- `structure.md`：补充「系统配置」页与 `Setting` 模型 / `settings` 表说明。
- `admin.md`：补全功能模块（工作台、Token 管理、系统配置、导航结构、品牌与主题、Docker 访问）。
- `install.md`：新增「Docker 部署」章节（启动、文件说明、自定义）与「Docker 生产部署」章节（服务器准备、生产配置、HTTPS、更新发布、备份恢复、常见问题）。
- `README.md`：新增 Docker 一键启动，测试用例数更新为 26。
- `changelog.md`：补录 v1.3.1 修复项，新增 v1.4.0。

---

## [v1.3.0] - 2026-08-21

### 修复

- **致命缺陷**：`app/Models/User.php` 中 `Laravel\Sanctum\PersonalAccessToken` 被重复 `use` 4 次，导致应用无法启动（`PHP Fatal error`），所有请求与测试均失败。清理为单次导入。

### 新增

- **CORS 跨域配置**：发布 `config/cors.php`，新增 `CORS_ALLOWED_ORIGINS` / `CORS_MAX_AGE` 环境变量，支持按来源收紧白名单（小程序默认 `*`）。
- **Sanctum Token 有效期**：`config/sanctum.php` 的 `expiration` 改为读取 `SANCTUM_TOKEN_EXPIRATION`，使 `.env` 文档约定生效。

### 修复

- **Filament v5 弃用 API**：`UserResource` 与 `TokenResource` 的表格动作由弃用的 `->actions()` / `->bulkActions()` 迁移至 `->recordActions()` / `->toolbarActions()`，消除弃用告警。

### 新增

- **CI 工作流**：落地 `.github/workflows/ci.yml`（GitHub Actions），`push` / `pull_request` 到 `main` 自动运行测试（SQLite `:memory:` + `RefreshDatabase`，无需外部数据库）。含 PHP 8.3 矩阵、Composer 缓存；`frontend` job 默认关闭，可按需启用。

### 文档

- 更新 `config.md`：补充 CORS 配置章节、修正章节序号。
- 更新 `structure.md`：补充 Filament 后台目录、CORS 配置、后台访问说明。
- 更新 `testing.md`：第 7 节「持续集成」与已落地的 `ci.yml` 对齐（文件名、PHP 矩阵、`cp .env.example` 流程、SQLite 内存库）。
- 根目录 `README.md` 与 `.env.example` 统一中文本地化（`zh_CN`）。

---

## [v1.2.0] - 2026-08-21

> 集成 FilamentPHP v5 管理后台（用户管理 + 数据统计仪表盘）。

### 新增

- **管理后台**：`/admin` 路径，FilamentPHP v5 构建。
  - 用户管理：`UserResource`（列表/创建/编辑/删除 + 多维筛选 + 手机号复制）。
  - Token 管理：`TokenResource`（查看/撤销 Sanctum Token）。
  - 仪表盘：用户统计卡片、近 7 天注册趋势、性别分布、最近用户表。
- **本地化**：`.env` 默认 `APP_LOCALE=zh_CN`。

---

## [v1.1.0] - 2026-08-21

### 新增

- **用户资料更新**：`PUT /api/user`，支持更新昵称、头像、性别、meta 扩展字段
- **手机号绑定**：`POST /api/user/phone`，通过微信新版接口获取并绑定手机号
- **API Resource**：`UserResource` 统一 JSON 响应格式
- **FormRequest 校验**：`LoginRequest`、`UpdateUserRequest` 参数校验类
- **全局异常处理**：统一 API 错误响应格式（code + message）
- **API 限流器**：`AppServiceProvider` 配置每分钟 60 次请求限制
- **功能测试**：23 个测试用例覆盖登录、用户、手机号接口

### 变更

- 所有 API 响应统一为 `{"code": 0, "message": "...", "data": {...}}` 格式
- `users` 表新增 `phone` 字段
- 错误响应增加 `code` 字段（如 40100、42200 等）

---

## [v1.0.0] - 2026-08-21

首个稳定骨架版本，提供微信小程序通用后台的完整基础能力。

### 新增

- **微信登录**：`POST /api/auth/login`，通过 `code2session` 换取 `openid`，签发 Sanctum Token
- **用户查询**：`GET /api/user`，获取当前登录用户信息
- **退出登录**：`POST /api/auth/logout`，吊销当前 Token
- **用户模型**：`users` 表扩展 `openid`、`unionid`、`nickname`、`avatar`、`gender`、`meta` 字段
- **配置驱动**：`.env` 中配置 `MINI_PROGRAM_APP_ID` / `MINI_PROGRAM_SECRET` 即可接入任意小程序
- **Sanctum 鉴权**：基于 Bearer Token 的 API 鉴权，适配小程序无 Cookie 场景
- **完整文档**：安装、配置、鉴权 API、数据库设计、目录结构、测试指南

### 技术栈

- Laravel 13.17+ / PHP 8.3+
- Laravel Sanctum 4.3+
- PHPUnit 12.5+
- Vite + Tailwind CSS
