# 微信小程序通用后台（mini-app-common）

> 基于 Laravel 13 + PHP 8.3 的「一套后台，接入任意微信小程序」通用后端解决方案。

本仓库提供一套**配置驱动**的微信小程序通用后端骨架：

- 通过 `mini_program.app_id / secret` 即可接入一个小程序，无需改代码
- 内置 `code2session` 登录换取 `openid` / `unionid`
- 基于 Laravel Sanctum 的 `Bearer Token` 鉴权（适配小程序无 Cookie 场景）
- 通用用户体系 + 可扩展的 `meta` 字段，覆盖 90% 小程序的起步需求
- 标准 REST 风格 API 路由骨架，方便挂载业务模块

---

## 文档导航

| 文档 | 内容 |
| --- | --- |
| [install.md](./install.md) | 环境要求、安装、部署、Git / 版本号规范 |
| [config.md](./config.md) | `.env` 配置项说明（微信小程序、数据库、鉴权） |
| [auth.md](./auth.md) | 微信登录流程图 + 完整 API 接口文档 |
| [database.md](./database.md) | 数据表结构设计 |
| [structure.md](./structure.md) | 目录结构与核心文件职责 |
| [testing.md](./testing.md) | 测试指南（单元测试 / 功能测试 / CI） |
| [changelog.md](./changelog.md) | 版本变更记录 |

---

## 技术栈

- **后端框架**：Laravel 13.26（PHP 8.3+）
- **鉴权**：Laravel Sanctum 4.x（API Token）
- **数据库**：默认 SQLite（开发），支持 MySQL / PostgreSQL（生产）
- **微信接口**：小程序 `code2session`（登录凭证校验）

---

## 快速开始

```bash
# 1. 克隆（或已在本地）
git clone git@github.com:leo0315code/mini-app-common.git

# 2. 安装依赖
composer install

# 3. 配置环境
cp .env.example .env
php artisan key:generate

# 4. 配置微信小程序
#    编辑 .env 设置 MINI_PROGRAM_APP_ID / MINI_PROGRAM_SECRET

# 5. 迁移数据库
php artisan migrate

# 6. 启动
php artisan serve
```

详细步骤见 [install.md](./install.md)。

---

## 版本与 Git 规范

- 仓库已关联远程：`git@github.com:leo0315code/mini-app-common.git`
- 采用 **语义化版本（SemVer）**，发布时打 `git tag`：
  - 首个稳定骨架：`v1.0.0`
  - 破坏性变更：`v2.0.0`，新功能：`v1.x.0`，修复：`v1.0.x`
- 提交信息建议遵循 **Conventional Commits**（见 install.md）。

---

## 适用场景

- 快速孵化多个微信小程序，共用一套登录与用户后台
- 作为中台 / 多租户小程序的通用底座
- 学习 Laravel + 微信生态集成的标准实践
