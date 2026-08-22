# 管理后台使用指南

## 概述

本项目使用 **FilamentPHP v5** 构建管理后台，品牌名「**宏图爱**」（主色调 Emerald），提供小程序用户管理、API Token 管理、系统配置与数据统计仪表盘。

## 访问地址

| 运行方式 | 地址 |
| --- | --- |
| 本地开发（`php artisan serve`） | http://localhost:8000/admin |
| Docker Compose | http://localhost:8080/admin |

## 默认账号

| 角色 | 邮箱 | 密码 |
|------|------|------|
| 管理员 | 453507012@qq.com | admin123 |

> 首次登录后请及时修改密码。管理员判定条件：`User::canAccessPanel()` 要求用户同时具备 `email` 与 `password`；普通小程序用户（仅 openid）无法进入后台。

## 导航结构

侧栏导航如下：

- **工作台**（顶部独立项）：数据总览仪表盘
- **系统管理**（分组）
  - 用户管理（`/admin/users`）
  - 角色管理（`/admin/roles`）
  - 菜单管理（`/admin/menus`）
  - Token 管理（`/admin/tokens`）
  - 系统配置（`/admin/system-config`）
- **内容运营**（分组）
  - 公告管理（`/admin/announcements`）
  - 文章管理（`/admin/articles`）
  - 分类管理（`/admin/categories`）
  - 站内通知（`/admin/notifications`）
- **用户反馈**（独立项）：`/admin/feedback`
- **审计日志**（独立项）：`/admin/audit-logs`
- **媒体管理**（独立项）：`/admin/media`

> 菜单根据当前用户的角色权限动态显示，无权访问的资源自动隐藏。详见 [RBAC 权限系统](./rbac.md)。

## 功能模块

### 1. 工作台（Dashboard）

访问 `/admin` 首页，展示小程序用户数据总览：

- **欢迎区**：顶部欢迎说明（可折叠），描述各区块用途
- **时间范围筛选**：右上角「筛选」弹窗，可选「近 7 天 / 近 30 天 / 近 90 天 / 全部」，默认近 30 天；选择会同步到 URL 并持久化到会话
- **用户统计卡片**：按所选范围显示**总用户数、时间范围内新增、手机绑定率**
- **运营指标卡片**（v1.10.0 新增）：**待处理反馈数**（pending 状态，>0 高亮 warning）、**通知已读率**（基于 `notification_user` 回执表）、**内容总量**（公告 + 文章）、**媒体占用**（字节数 + 文件数）、**今日 API 调用**（Token 当日活跃）、**封禁用户数**
- **注册趋势图**：所选天数内的用户注册折线图
- **性别分布图**：按时间段过滤的性别占比
- **最近注册用户**：最新 10 名小程序用户，可点击跳转用户管理
- **待处理反馈表**（v1.10.0 新增）：最新 10 条 pending 反馈，含类型徽标 / 内容 / 联系方式 / 提交时间，可一键「去处理」跳转反馈编辑页
- 数据每 **30 秒自动刷新**

### 2. 用户管理

路径：`/admin/users`

#### 表格列

| 列名 | 说明 | 特性 |
|------|------|------|
| ID | 用户 ID | 可排序、可切换显示 |
| 头像 | 用户头像 | 圆形显示，默认随机头像 |
| 昵称 | 用户昵称 | 可搜索、可切换 |
| 手机号 | 绑定手机号 | 可搜索、可一键复制 |
| 性别 | 0未知/1男/2女 | 可切换 |
| 小程序用户 | 是否有 openid | 图标显示 |
| 注册时间 | 用户注册时间 | 可排序、可切换 |
| 更新时间 | 最后修改时间 | 默认隐藏 |

#### 筛选器

- **性别**：未知 / 男 / 女
- **小程序用户**：是 / 否 / 全部
- **已绑定手机**：是 / 否 / 全部
- **注册时间范围**：开始 / 结束日期

#### 操作

- **创建用户**：手动添加，表单字段包含基本信息（OpenID、UnionID、昵称、手机号、性别、头像）与扩展信息（Meta 键值对）；已存在用户的 OpenID 不可编辑
- **编辑**：修改昵称、手机号、性别、头像、Meta 等
- **删除**：单个或批量删除

### 3. 角色管理

路径：`/admin/roles`

#### 功能说明

管理后台角色及其菜单权限，支持级联选择和预设模板。

#### 表格列

| 列名 | 说明 |
|------|------|
| ID | 角色 ID |
| 名称 | 角色显示名 |
| 标识 | 角色 slug |
| 成员数 | 拥有该角色的用户数 |
| 权限数 | 已分配的菜单权限数 |
| 创建时间 | 角色创建时间 |

#### 操作

- **创建角色**：填写名称/标识/说明 → 选择预设模板（可选）→ 分配菜单权限
- **编辑**：修改角色信息和菜单权限
- **克隆**：复制角色及其全部菜单权限
- **删除**：删除角色及其关联的菜单权限（不影响用户数据）

#### 菜单权限分配

菜单权限以树形结构展示：

- **级联选择**：勾选父菜单自动选中所有子菜单，取消父菜单自动取消子菜单
- **不确定状态**：部分子项选中时父级显示 indeterminate 状态
- **统计卡片**：顶部显示总菜单数 / 已选数 / 进度条
- **工具栏**：全选 / 清空 / 展开 / 折叠
- **预设模板**：快速创建常见角色（内容编辑、运营、审核员、系统管理员）

> 详细权限机制见 [RBAC 权限系统](./rbac.md)。

### 4. 菜单管理

路径：`/admin/menus`

#### 功能说明

管理后台导航菜单结构和权限键。新增菜单后自动分配给 `super-admin` 角色。

#### 表格列

| 列名 | 说明 |
|------|------|
| 名称 | 菜单显示名 |
| 标识 | 菜单 slug |
| 权限键 | 如 `article.view`、`user.manage` |
| 图标 | Filament 图标 |
| 路由 | Filament 路由路径 |
| 排序 | 显示顺序 |
| 可见 | 侧栏是否显示 |
| 启用 | 是否启用 |

#### 操作

- **创建菜单**：填写菜单信息，支持设置父菜单（形成树形结构）
- **编辑**：修改菜单属性
- **删除**：级联删除子菜单 + 清除角色关联 + 清除权限缓存

#### 菜单层级

```
仪表盘
├── 文章管理
│   ├── 文章列表 (article.view)
│   ├── 文章管理 (article.manage)
│   ├── 分类管理 (category.view)
│   └── 分类管理权限 (category.manage)
├── 用户管理
│   ├── 用户列表 (user.view)
│   ├── 用户管理权限 (user.manage)
│   └── 角色管理 (role.view)
├── 反馈管理 (feedback.view / feedback.manage)
├── 公告管理 (announcement.view / announcement.manage)
└── ...
```

### 5. Token 管理

路径：`/admin/tokens`

管理小程序端登录签发的 Sanctum API Token（侧栏显示 Token 总数角标）。

#### 表格列

| 列名 | 说明 |
|------|------|
| ID | Token ID |
| 用户 | Token 所属用户（昵称/姓名） |
| Token 名称 | 签发时指定的名称 |
| 最后使用 | 最近一次调用时间，未使用显示「从未使用」 |
| 创建时间 | 签发时间 |

#### 操作

- **撤销**：删除单个 Token，对应设备立即失效
- **批量撤销**：选中多条批量删除
- 不可手动创建（Token 由 `POST /api/auth/login` 签发），按创建时间倒序排列

### 6. 用户封禁 / 解封

路径：`/admin/users`

对违规用户可在线封禁，封禁后该用户：

- 无法登录小程序（`POST /api/auth/login` 返回 `40301 账号已被封禁`）；
- 即使持有旧 Token，调用任何受保护接口也会被拦截（同样返回 `40301`）；
- 封禁时其全部 API Token 立即吊销，已登录设备即刻失效。

| 入口 | 说明 |
|------|------|
| 列表「状态」列 | 徽标显示 `正常`（绿）/ `已封禁`（红），可按状态筛选 |
| 行内「封禁」 | 带二次确认，可填写封禁原因（写入 `ban_reason`） |
| 行内「解封」 | 仅对已封禁用户显示，点击恢复正常 |

### 7. 系统配置

路径：`/admin/system-config`

集中维护可后台变更的系统参数，保存后写入 `settings` 表（`Setting` 模型），并同步到当前请求的运行时 config。

| 分组 | 配置项 | 说明 |
|------|--------|------|
| 微信小程序 | AppID / AppSecret | `code2session` 登录与手机号解密凭证；与 `.env` 的 `MINI_PROGRAM_APP_ID` / `MINI_PROGRAM_SECRET` 等效，数据库值优先 |
| 跨域（CORS） | 允许的来源 | 逗号分隔的域名白名单；留空或 `*` 表示不限制 |
| | 预检缓存秒数 | `Access-Control-Max-Age`，默认 0 |
| 安全 | Token 有效期（分钟） | 0 或留空表示永久有效（Sanctum `expiration`） |
| | Token 前缀 | Sanctum `token_prefix`，留空表示不设前缀 |
| 站点 | 后台品牌名 | 后台左上角 LOGO 文本，默认「宏图爱」 |

> 保存行为：配置即时写入数据库并作用于当前请求；CORS 与 Token 相关配置需在下次部署 / 重启后按数据库值生效（`config/cors.php`、`config/sanctum.php` 启动时读取 `Setting`）。

## 品牌与主题

- **品牌名**：默认「宏图爱」，可在「系统配置 → 站点」后台修改
- **主题色**：主色为 Emerald（翠绿），定义于 `app/Providers/Filament/AdminPanelProvider.php`
- **主题样式**：`resources/css/filament/admin/theme.css`，修改后执行 `npm run build`

## 开发指南

### 添加新资源

```bash
php artisan make:filament-resource Order
```

```php
// app/Filament/Resources/OrderResource.php
public static function form(Schema $schema): Schema { ... }
public static function table(Table $table): Table { ... }
```

### 添加新小组件

```bash
php artisan make:filament-widget OrderStats --stats-overview   # 统计卡片
php artisan make:filament-widget OrderChart --chart            # 图表
```

### 导航分组与排序

分组资源置于侧栏「系统管理」：

```php
protected static string|UnitEnum|null $navigationGroup = '系统管理';
protected static ?int $navigationSort = 3;   // 组内顺序
```

### 本地化

后台已配置为中文（`zh_CN`），修改 `.env` 的 `APP_LOCALE` 后执行 `php artisan optimize:clear`。

## 测试数据

填充演示数据：

```bash
php artisan db:seed
```

将创建：

- 1 个管理员账号（`453507012@qq.com` / `admin123`）
- 20 个测试小程序用户（含随机头像、昵称、手机号）

## 安全建议

1. **修改默认密码**：首次登录后立即修改
2. **访问控制**：`User::canAccessPanel()` 仅允许具备 `email` + `password` 的用户进入后台
3. **HTTPS**：生产环境务必启用 HTTPS，并配置小程序「request 合法域名」
4. **定期备份**：定期备份数据库

## 常见问题

### Q: 后台显示无样式？

A: 运行以下命令构建资源：

```bash
php artisan filament:assets
npm run build
```

### Q: 如何添加新的后台用户？

A: 使用 Artisan 命令（需提供邮箱与密码）：

```bash
php artisan make:filament-user
```

或执行 `php artisan db:seed` 直接创建默认管理员。

### Q: 如何自定义品牌名称？

A: 后台进入「系统配置 → 站点」修改品牌名即可；或编辑 `app/Providers/Filament/AdminPanelProvider.php`：

```php
->brandName('你的品牌名')
```

### Q: Docker 部署下如何进入后台？

A: `docker compose up -d --build` 后访问 http://localhost:8080/admin ，管理员账号同上。

## 相关文档

- [RBAC 权限系统](./rbac.md)
- [认证接口文档](./auth.md)
- [数据库设计](./database.md)
- [项目结构](./structure.md)
- [测试指南](./testing.md)
- [安装与部署](./install.md)
