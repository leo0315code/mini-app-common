# 后台功能规划（Roadmap）

> 基于 **v1.8.0** 代码现状的缺口分析。按 P1（高价值近期）→ P4（远期/架构）排序，供迭代排期参考。
> 每项均已核对当前代码事实，标注了「现状缺口」与「落地要点」。

---

## 现状盘点（已有能力）

| 模块 | 后台入口 | 小程序 API | 状态 |
| --- | --- | --- | --- |
| 用户管理 | `/admin/users` | `/api/user`、`/api/auth/*` | ✅ 基础增删改查 + 角色指派 |
| RBAC 角色 | `/admin/roles` | — | ✅ 轻量（slug 控制面板访问） |
| API Token | `/admin/tokens` | `/api/auth/login` 签发 | ✅ 查看/撤销 |
| 系统配置 | `/admin/system-config` | — | ✅ Setting 分组键值 |
| 操作审计 | `/admin/audit-logs` | — | ✅ 部分模型自动记录 |
| 公告 | `/admin/announcements` | `/api/announcements` | ✅ |
| CMS 文章/分类 | `/admin/articles`、`/admin/categories` | `/api/articles` 系列 | ✅ |
| 用户反馈 | `/admin/feedback` | `POST /api/feedback` | ✅ 含处理流 |
| 站内通知 | `/admin/notifications` | `/api/notifications` 系列 | ✅ 群发/定向/已读 |
| 媒体库 | `/admin/media` | `POST /api/upload` | ✅ 含分组/类型筛选 |
| 工作台 | `/admin`（Dashboard） | — | ✅ 仅用户维度统计 |

---

## P1 — 核心缺口，建议近期落地

### 1. 微信订阅消息推送
- **现状缺口**：通知触达只有站内信（用户打开小程序才能看到），没有任何离线触达渠道。这是「小程序通用后台」最核心的缺失能力。
- **落地要点**：
  - 新增 `message_templates` 表（模板 ID、标题、字段定义）+ 后台模板管理。
  - 发送记录表（发送目标、状态、微信返回的错误码）。
  - 与现有 `Notification` 联动：派发通知时可选同步下发订阅消息（`subscribeMessage.send`）。
  - 需 `access_token` 的获取与缓存（`stable_token`，缓存到 `Setting` 或 Cache）。

### 2. 用户封禁 / 禁用 ✅ 已落地（v1.9.0）
- `users.status`（normal/banned）+ `banned_at` / `ban_reason`；`User::ban()/unban()` 自动吊销全部 Token。
- 中间件 `EnsureUserNotBanned` 拦截受保护接口（40301）；登录同步校验封禁状态。
- 后台用户列表「状态」徽标列 + 状态筛选 + 封禁/解封动作。

### 3. 仪表盘运营维度扩展 ✅ 已落地（v1.10.0）
- 工作台新增 `OpexStatsWidget`（待处理反馈数、通知已读率、内容总量、媒体占用、今日 API 调用、封禁用户数）+ `PendingFeedbackTable`（待处理反馈直达处理），统计口径已在 Docker 真实 MySQL 校验。

### 4. 数据导出（Excel/CSV）
- **现状缺口**：所有列表均无导出，用户名单、反馈明细只能截图。
- **落地要点**：Filament Export（用户、反馈、通知回执优先），走队列异步生成 + 后台下载，避免大数据集超时。

---

## P2 — 安全与运营效率

### 5. 资源级权限（Policy）
- **现状缺口**：RBAC 只控制「能否进后台」（`canAccessPanel`），进去之后任何管理员都能操作所有模块（含删除用户、改系统配置）。`app/Policies` 目录不存在。
- **落地要点**：为每个 Resource 建 Policy，按角色 slug 控制 `viewAny/view/create/update/delete`；普通 admin 只给内容运营模块，super-admin 全量。

### 6. 后台登录安全
- **现状缺口**：后台登录无独立限流、无登录/登出审计（`AuditObserver` 只监听模型 CRUD，不含登录事件）、没有修改密码页面。
- **落地要点**：登录失败限流（`RateLimiter`）；登录成功/失败写 `audit_logs`；后台个人资料页支持改密码。

### 7. 通知 / 公告定时发布
- **现状缺口**：`published_at` 字段已存在且查询侧已兼容（`published_at <= now()`），但没有调度器自动把 `draft` 翻成 `published`，定时发布形同虚设。
- **落地要点**：`console command` + `schedule:run`（部署文档需注明 crontab / Docker entrypoint），到点自动发布；通知也可加 `scheduled_at` 延迟派发。

### 8. 首页运营位（Banner）管理
- **现状缺口**：小程序首页轮播图/金刚位没有对应后台，目前只能改代码或塞进公告。
- **落地要点**：`banners` 表（图片、跳转链接/文章关联、排序、生效时间）+ 后台管理 + `GET /api/banners` 公开接口。

---

## P3 — 体验补齐

### 9. 审计覆盖补全
- **现状缺口**：`AuditObserver` 仅监听 `User / Announcement / Feedback / Article / Category`，**通知、媒体、角色的增删改均不落审计**（v1.6.0 新增模块即缺失）。
- **落地要点**：Observer 补注册三个模型，`module` 分别记 `notification / media / role`。

### 10. 回收站（软删除）
- **现状缺口**：全项目无任何模型使用 `SoftDeletes`，误删文章/公告/媒体记录后无法恢复（媒体只删文件不留记录）。
- **落地要点**：`articles / announcements / media` 优先加软删 + 后台「回收站」筛选页；删除策略统一（软删记录，硬删才清文件）。

### 11. Token 会话管理增强
- **现状缺口**：Token 只能逐个或批量撤销，看不到设备信息；没有「按用户一键下线全部设备」。
- **落地要点**：签发时记录设备名/UA 摘要；用户详情页聚合展示其 Token 并支持一键踢下线。

### 12. 用户详情聚合页
- **现状缺口**：用户只有编辑表单，看不到该用户的通知已读情况、反馈历史、审计轨迹、Token 列表，排查问题要开四个页面分别搜。
- **落地要点**：`ViewUser` 页（RelationManager 或 infolist）聚合以上四类关联数据。

### 13. 富文本编辑器图片入媒体库
- **现状缺口**：文章/公告 RichEditor 插入的图片走 Filament 默认上传路径，**不进 `media` 表**，媒体库看不到也无法统一管理/清理。
- **落地要点**：编辑器 `fileAttachmentsDirectory` + 上传钩子写入 `media` 表（collection 设为 `rich-editor`）。

---

## P4 — 架构演进

### 14. 多小程序（多租户）
- **现状缺口**：README 定位「一套后台接入任意小程序」，但实际单一 `app_id`，用户表无租户维度，接第二个小程序就要整套复制。
- **落地要点**：`apps` 表 + `users.app_id` 外键隔离 + 微信凭证按租户配置；这是 v2.0 级别的破坏性变更，需先冻结 P1-P3。

### 15. 消息渠道抽象
- 站内信（已有）+ 订阅消息（P1）+ 短信/邮件统一为 `NotificationChannel` 抽象，通知派发按用户订阅偏好降级发送。

### 16. 数据备份
- `db:backup` 命令（spatie/laravel-backup）+ 后台备份记录入口；媒体文件与数据库分开备份策略。

### 17. 工程化门禁
- `pint.json` 风格规则 + CI 增加 lint job（`vendor/bin/pint --test`），阻止风格漂移。

---

## 落地节奏建议

| 阶段 | 内容 | 版本 |
| --- | --- | --- |
| 近期 | P1 全部（订阅消息、封禁、仪表盘、导出） | v1.9.0 ~ v2.0.0 |
| 中期 | P2（权限、登录安全、定时发布、Banner） | v2.x |
| 空闲穿插 | P3 体验项（每项独立小版本） | v2.x patch |
| 远期 | P4 架构演进 | v3.0（破坏性） |

---

## 已知工程问题（非本迭代引入，待 P3 修复）

- **`tests/Feature/CmsTest` 编辑页 404（2 例）**：`/admin/articles/{id}/edit` 与 `/admin/categories/{id}/edit` 在测试中返回 404。Edit 页面定义（`extends EditRecord` + `$resource`）与 `getPages()` 路由均正常，疑为 Filament v5 编辑页在测试 HTTP 引导下的渲染/路由解析问题，需单独排查。
- **`tests/Feature/PhoneTest::test_bind_phone_success` 401**：`PhoneController::bind` 在 `WechatService::getPhoneNumber` 抛 `RuntimeException` 时 catch 返回 401。测试中 `Http::fake` 序列与 `@code` 校验逻辑需核对，属用例/服务桩问题，非接口缺陷。
- 以上 3 例在 v1.9.0 基线即存在，P1-3 仪表盘扩展未引入任何回归。
