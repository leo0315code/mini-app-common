# 配置说明

所有配置通过项目根目录 `.env` 管理。以下列出与本项目相关的配置项。

---

## 1. 微信小程序配置

用于小程序 `code2session` 登录，向微信服务器换取 `openid` / `unionid`。

```env
# 小程序 AppID（公众平台 → 开发 → 开发设置）
MINI_PROGRAM_APP_ID=

# 小程序 AppSecret（同页面获取，敏感，切勿入库）
MINI_PROGRAM_SECRET=
```

对应代码读取位置：`config/services.php` 的 `mini_program` 段：

```php
'mini_program' => [
    'app_id'  => env('MINI_PROGRAM_APP_ID'),
    'secret' => env('MINI_PROGRAM_SECRET'),
],
```

> 设计为「配置驱动」：换一个小程序，只改这两个值即可，后端代码无需改动。

---

## 2. 应用与数据库配置

`.env` 标准片段：

```env
APP_NAME=MiniAppCommon
APP_ENV=local
APP_KEY=base64:...           # php artisan key:generate 自动生成
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite         # 开发用 sqlite；生产可改 mysql
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=mini_app_common
# DB_USERNAME=root
# DB_PASSWORD=
```

SQLite 开发库位置：`database/database.sqlite`（首次 `migrate` 自动创建）。

---

## 3. 鉴权配置（Sanctum）

本项目使用 Laravel Sanctum 的 API Token 模式，适配小程序无 Cookie / 无 Session 的场景。

### 3.1 模型启用 Token 能力

`app/Models/User.php` 已引入 `HasApiTokens` trait：

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}
```

### 3.2 路由保护

在路由文件中为受保护接口注册 `auth:sanctum` 中间件：

```php
Route::middleware('auth:sanctum')->get('/user', fn ($request) => $request->user());
```

### 3.3 令牌有效期（可选）

`config/sanctum.php` 中可设置 `expiration`（分钟），默认 `null` 表示永不过期。
建议生产环境设置合理过期时间并在客户端做刷新逻辑：

```php
'expiration' => 60 * 24 * 7, // 7 天
```

也可通过 `.env` 中的 `SANCTUM_TOKEN_EXPIRATION` 控制（需在 `config/sanctum.php` 中引用）：

```env
# Token 有效期（分钟），留空表示永不过期
SANCTUM_TOKEN_EXPIRATION=10080   # 7 天 = 7 * 24 * 60
```

---

## 4. 跨域配置（CORS）

微信小程序 `wx.request` 的 origin 为 `https://servicewechat.com` 且不固定，因此默认允许所有来源。

`config/cors.php` 已通过环境变量驱动：

```php
'allowed_origins' => env('CORS_ALLOWED_ORIGINS')
    ? explode(',', env('CORS_ALLOWED_ORIGINS'))
    : ['*'],

'max_age' => env('CORS_MAX_AGE', 0),
```

```env
# 留空 = 允许所有来源（小程序默认）
CORS_ALLOWED_ORIGINS=
# 接入自有 Web 管理端 / H5 时收紧为具体域名，逗号分隔
# CORS_ALLOWED_ORIGINS=https://a.com,https://b.com
CORS_MAX_AGE=0
```

> Laravel 13 默认已全局注册 `HandleCors` 中间件，且 `paths` 涵盖 `api/*`，无需额外挂中间件。

---

## 5. 微信接口地址

`code2session` 请求地址（已由 `WechatService` 封装，无需手动调用）：

```
GET https://api.weixin.qq.com/sns/jscode2session
    ?appid=APPID
    &secret=SECRET
    &js_code=登录时前端拿到的 code
    &grant_type=authorization_code
```

返回示例：

```json
{
  "openid": "oABC123...",
  "session_key": "tiihtNczf5jigQRKg......",
  "unionid": "oXYZ..."   // 已绑定开放平台时返回
}
```

---

## 5. 中间件配置

`bootstrap/app.php` 已注册以下关键中间件（Laravel 13 默认包含）：

| 中间件 | 作用 |
| --- | --- |
| `auth:sanctum` | 校验 Bearer Token，注入 `$request->user()` |
| `api` (`throttle:api`) | API 限流，防止刷接口 |
| `EnsureFrontendRequestsAreStateful` | Sanctum SPA 状态保持（可选） |

> 所有小程序接口统一加 `api` 前缀与 `throttle:api` 限流保护。
