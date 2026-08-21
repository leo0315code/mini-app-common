# 数据库设计

本后台采用「通用用户表 + 扩展字段」设计，优先覆盖绝大多数小程序的起步需求，后续业务表按需挂载。

> 开发默认 SQLite（`database/database.sqlite`），迁移脚本兼容 MySQL / PostgreSQL。

---

## 1. 数据表清单

| 表名 | 用途 | 来源 |
| --- | --- | --- |
| `users` | 微信小程序用户主表 | Laravel 默认 + 扩展字段 |
| `personal_access_tokens` | Sanctum 令牌表 | Sanctum 迁移 |
| `cache` / `jobs` | Laravel 基础设施 | 默认迁移 |
| `settings` | 系统配置键值表（分组 + JSON value） | v1.4.0 迁移 |
| `audit_logs` | 操作审计日志 | v1.5.0 迁移 |
| `announcements` | 公告/通知 | v1.5.0 迁移 |
| `feedback` | 用户反馈 | v1.5.0 迁移 |

---

## 2. users（用户表）

在 Laravel 默认 `users` 表基础上扩展微信相关字段。

| 字段 | 类型 | 约束 | 说明 |
| --- | --- | --- | --- |
| `id` | bigint | PK, 自增 | 用户主键 |
| `openid` | varchar(64) | **唯一索引** | 小程序用户唯一标识（同一小程序内唯一） |
| `unionid` | varchar(64) | 可空, 唯一 | 开放平台统一 ID（多端互通时） |
| `nickname` | varchar(64) | 可空 | 昵称 |
| `avatar` | varchar(255) | 可空 | 头像 URL |
| `gender` | tinyint | 默认 0 | 性别：0未知 / 1男 / 2女 |
| `phone` | varchar(20) | 可空 | 手机号（解密后存储） |
| `meta` | json | 可空 | 业务扩展字段（灵活存储） |
| `email` | varchar(255) | 可空 | 保留（默认迁移字段） |
| `email_verified_at` | timestamp | 可空 | 保留 |
| `password` | varchar(255) | 可空 | 预留（小程序可纯微信登录，无需密码） |
| `remember_token` | varchar(100) | 可空 | 保留 |
| `created_at` / `updated_at` | timestamp | — | 时间戳 |

### 设计要点

- **`openid` 唯一索引**：保证同一小程序用户不重复建号，登录时 `firstOrCreate` 幂等。
- **`unionid` 唯一**：便于同一主体下的 App / 公众号 / 小程序用户合并。
- **`meta` 用 JSON**：小程序的业务字段千差万别，用 JSON 兜底避免频繁加列。
- **`password` 可空**：纯微信授权登录场景不需要密码；若后续接入手机号+密码登录，该字段启用。

### 迁移示意（app/../create_users_table.php 实际以代码为准）

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('openid')->unique();
    $table->string('unionid')->nullable()->unique();
    $table->string('nickname')->nullable();
    $table->string('avatar')->nullable();
    $table->tinyInteger('gender')->default(0);
    $table->string('phone', 20)->nullable()->comment('手机号（解密后存储）');
    $table->json('meta')->nullable();
    // 保留 Laravel 默认字段
    $table->string('email')->nullable();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password')->nullable();
    $table->rememberToken();
    $table->timestamps();
});
```

---

## 3. personal_access_tokens（令牌表，Sanctum）

| 字段 | 说明 |
| --- | --- |
| `id` | PK |
| `tokenable_type` / `tokenable_id` | 关联用户（多态，指向 `users`） |
| `name` | 令牌名称（本项目固定 `mini-program`） |
| `token` | 哈希后的令牌 |
| `abilities` | 权限范围（默认 `["*"]`） |
| `last_used_at` / `expires_at` | 使用时间 / 过期时间 |
| `created_at` / `updated_at` | 时间戳 |

> 登录时签发 `name=mini-program` 的 Token，退出时吊销当前 Token。

---

## 4. 索引与性能

- `users.openid` 唯一 — 登录查询 O(1)。
- `users.unionid` 唯一 — 跨端合并查询。
- 后续业务表建议对 `user_id` 建普通索引。

---

## 5. 后续可扩展表（建议，不在 v1.0.0）

| 表 | 用途 |
| --- | --- |
| `user_phones` | 解密后的手机号（需 `session_key`，单独授权） |
| `configs` | 小程序端配置（开关、文案） |
| `*_business` | 具体业务表（订单、内容等） |

---

## 6. v1.5.0 新增业务表

### `settings`（系统配置，v1.4.0）
键值表，`group` + `key` 唯一，`value` 为 JSON（`array` cast）。由 `App\Models\Setting` 访问：`getGroup($group, $defaults)` / `setGroup($group, $data)`。

### `audit_logs`（操作审计，v1.5.0）

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `id` | bigint | PK |
| `type` | varchar | create/update/delete/login/config |
| `module` | varchar | user/token/announcement/feedback/system |
| `action` | varchar | 动作描述 |
| `description` | text | 可读描述 |
| `old_data` / `new_data` | json | 变更前 / 后（可空） |
| `subject_type` / `subject_id` | varchar / bigint | 多态关联被操作对象 |
| `user_id` | bigint | 操作人（可空） |
| `url` / `ip` | varchar | 请求地址 / IP |
| `created_at` | timestamp | 时间（按日建索引） |

### `announcements`（公告，v1.5.0）

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `title` | varchar(120) | 标题 |
| `content` | longtext | 正文（RichEditor） |
| `type` | varchar | notice / activity / update |
| `status` | varchar | draft / published / offline |
| `published_at` | timestamp | 发布时间（可空） |
| `created_by` | bigint | 发布人 FK → users |

### `feedback`（用户反馈，v1.5.0）

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `user_id` | bigint | 提交用户 FK → users（可空，游客可投） |
| `type` | varchar | suggestion / bug / complaint / other |
| `content` | text | 反馈内容 |
| `contact` | varchar | 联系方式（可空） |
| `status` | varchar | pending / processing / resolved / rejected |
| `handle_note` | text | 处理备注 / 回复内容 |
| `handled_by` | bigint | 处理人 FK → users（可空） |
| `handled_at` | timestamp | 处理时间（可空） |
