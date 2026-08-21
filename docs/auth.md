# 鉴权与 API 文档

本文档说明微信小程序登录流程与全部接口约定。

---

## 1. 登录流程（code2session）

小程序端无法像 Web 一样直接拿到用户身份，需通过临时 `code` 换 `openid`。

```
┌──────────┐     ① wx.login() 获取 code     ┌──────────────┐
│ 小程序端  │ ────────────────────────────▶ │  微信客户端   │
└──────────┘                                └──────────────┘
     │                                                │
     │ ② POST /api/auth/login { code }                │
     ▼                                                ▼
┌────────────────────┐   ③ code2session     ┌─────────────────────┐
│  Laravel 后台       │ ───────────────────▶ │  微信服务器          │
│  WechatService     │ ◀─────────────────── │  openid/session_key  │
└────────────────────┘   ④ 返回 openid       └─────────────────────┘
     │
     │ ⑤ 按 openid 查找/创建用户，签发 Sanctum Token
     ▼
┌──────────┐   ⑥ 返回 { token, user }      ┌──────────┐
│ 小程序端  │ ◀─────────────────────────── │  存储token │
└──────────┘   后续请求 Header 带 Bearer    └──────────┘
```

### 关键说明

- 步骤③对用户不可见，仅在服务端调用微信，密钥不暴露给前端。
- `openid` 作为小程序用户唯一标识，建立本地用户记录（`users.openid` 唯一索引）。
- `session_key` 仅用于解密敏感数据（如手机号），本项目不落库，需要时可在 `WechatService` 中扩展。

---

## 2. 接口总览

基地址：`/api`

| 方法 | 路径 | 鉴权 | 说明 |
| --- | --- | --- | --- |
| POST | `/api/auth/login` | 否 | 微信 `code` 登录，换取 Token |
| GET | `/api/user` | 是 | 获取当前登录用户信息 |
| PUT | `/api/user` | 是 | 更新当前用户资料 |
| POST | `/api/user/phone` | 是 | 绑定手机号 |
| POST | `/api/auth/logout` | 是 | 注销当前 Token |

> 后续业务模块（如内容、订单）均以 `/api` 为前缀、并在 `auth:sanctum` 中间件下扩展。

---

## 3. 接口详情

### 3.1 微信登录

`POST /api/auth/login`

**请求体**：

```json
{
  "code": "081abc..."
}
```

**成功响应** `200`：

```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "token": "1|abcdefghijklmn...",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "openid": "oABC123...",
      "unionid": null,
      "nickname": null,
      "avatar": null,
      "gender": 0,
      "phone": null,
      "meta": null,
      "created_at": "2026-08-21T10:00:00.000000Z",
      "updated_at": "2026-08-21T10:00:00.000000Z"
    }
  }
}
```

**失败响应**：

| HTTP | 场景 | body |
| --- | --- | --- |
| 422 | 缺少 `code` | `{"code":42200,"message":"参数校验失败","errors":{...}}` |
| 401 | 微信返回 code 无效 | `{"code":40100,"message":"微信登录失败：invalid code (40029)"}` |

**错误码透传**：微信接口错误（如 `40029 code 无效`、`45011 频率限制`）原样以 message 返回，便于排查。

---

### 3.2 获取当前用户

`GET /api/user`

**请求头**：`Authorization: Bearer <token>`

**响应** `200`：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1,
    "openid": "oABC123...",
    "unionid": null,
    "nickname": "微信用户",
    "avatar": "https://...",
    "gender": 0,
    "phone": null,
    "meta": null,
    "created_at": "2026-08-21T10:00:00.000000Z",
    "updated_at": "2026-08-21T10:00:00.000000Z"
  }
}
```

---

### 3.3 退出登录

`POST /api/auth/logout`

**请求头**：`Authorization: Bearer <token>`

**响应** `200`：

```json
{
  "message": "已退出登录"
}
```

> 实现：`$request->user()->currentAccessToken()->delete();` 仅吊销当前 Token（不影响其它端登录）。

---

### 3.4 更新用户资料

`PUT /api/user`

**请求头**：`Authorization: Bearer <token>`

**请求体**（所有字段可选）：

```json
{
  "nickname": "新昵称",
  "avatar": "https://example.com/avatar.png",
  "gender": 1,
  "meta": {"level": 10, "vip": true}
}
```

**响应** `200`：

```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": 1,
    "nickname": "新昵称",
    "avatar": "https://example.com/avatar.png",
    "gender": 1,
    "meta": {"level": 10, "vip": true},
    ...
  }
}
```

**校验规则**：

| 字段 | 规则 | 说明 |
| --- | --- | --- |
| `nickname` | nullable, string, max:64 | 昵称 |
| `avatar` | nullable, url, max:512 | 头像 URL |
| `gender` | nullable, integer, in:0,1,2 | 性别：0未知/1男/2女 |
| `meta` | nullable, array | 业务扩展字段 |

---

### 3.5 绑定手机号

`POST /api/user/phone`

**请求头**：`Authorization: Bearer <token>`

**请求体**：

```json
{
  "code": "手机号授权code"
}
```

> 小程序端调用 `wx.getPhoneNumber()` 获取 code，传给后端。

**响应** `200`：

```json
{
  "code": 0,
  "message": "手机号绑定成功",
  "data": {
    "id": 1,
    "phone": "13800138000",
    ...
  }
}
```

**失败响应**：

| HTTP | 场景 | body |
| --- | --- | --- |
| 422 | 缺少 `code` | `{"code":42200,"message":"参数校验失败"}` |
| 401 | 微信接口错误 | `{"code":40101,"message":"获取手机号失败：..."}` |

---

## 4. 错误约定

- 所有接口统一返回 JSON，格式为 `{"code": 错误码, "message": "描述", "data": ...}`。
- 业务错误通过 HTTP 状态码 + `code` 字段双重表达：

| HTTP 状态码 | code | 说明 |
| --- | --- | --- |
| 200 | 0 | 成功 |
| 401 | 40100 | 未授权/登录失败 |
| 401 | 40101 | 手机号授权失败 |
| 403 | 40300 | 禁止访问 |
| 404 | 40400 | 接口不存在 |
| 405 | 40500 | 请求方法不允许 |
| 422 | 42200 | 参数校验失败 |
| 429 | 42900 | 请求过于频繁 |
| 500 | 50000 | 服务器内部错误 |

- 开发中会自动返回异常栈（`.env` 中 `APP_DEBUG=true`），生产关闭。

---

## 5. 客户端接入示例（小程序 JS）

```javascript
// 登录
wx.login({
  success: async (res) => {
    const r = await wx.request({
      url: 'https://你的域名/api/auth/login',
      method: 'POST',
      data: { code: res.code },
    });
    wx.setStorageSync('token', r.data.token);
  },
});

// 携带 Token 请求受保护接口
wx.request({
  url: 'https://你的域名/api/user',
  header: { Authorization: 'Bearer ' + wx.getStorageSync('token') },
});
```
