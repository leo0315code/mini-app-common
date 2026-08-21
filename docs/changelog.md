# CHANGELOG

本项目遵循 [语义化版本（SemVer）](https://semver.org/lang/zh-CN/) 规范。

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
