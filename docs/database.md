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
| `audit_logs` | 操作审计 |
| `*_business` | 具体业务表（订单、内容等） |
