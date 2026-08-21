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
  "token": "1|abcdefghijklmn...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "openid": "oABC123...",
    "unionid": null,
    "nickname": null,
    "avatar": null,
    "created_at": "2026-08-21T10:00:00.000000Z"
  }
}
```

**失败响应**：

| HTTP | 场景 | body |
| --- | --- | --- |
| 422 | 缺少 `code` | `{"message":"The code field is required.","errors":{...}}` |
| 401 | 微信返回 code 无效 | `{"message":"微信登录失败：invalid code"}` |

**错误码透传**：微信接口错误（如 `40029 code 无效`、`45011 频率限制`）原样以 message 返回，便于排查。

---

### 3.2 获取当前用户

`GET /api/user`

**请求头**：`Authorization: Bearer <token>`

**响应** `200`：

```json
{
  "id": 1,
  "openid": "oABC123...",
  "unionid": null,
  "nickname": "微信用户",
  "avatar": "https://...",
  "meta": null,
  "created_at": "2026-08-21T10:00:00.000000Z"
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

## 4. 错误约定

- 所有接口统一返回 JSON。
- 业务错误通过 HTTP 状态码表达：`401` 未授权、`403` 禁止、`422` 参数校验失败、`500` 服务器异常。
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
