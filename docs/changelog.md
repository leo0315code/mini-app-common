# CHANGELOG

本项目遵循 [语义化版本（SemVer）](https://semver.org/lang/zh-CN/) 规范。

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
