# 管理后台使用指南

## 概述

本项目使用 **FilamentPHP v5** 构建管理后台，提供用户管理、数据统计等功能。

## 访问地址

```
http://localhost:8000/admin
```

## 默认账号

| 角色 | 邮箱 | 密码 |
|------|------|------|
| 管理员 | admin@example.com | admin123 |

> 首次登录后请及时修改密码。

## 功能模块

### 1. 仪表盘（Dashboard）

访问 `/admin` 首页显示：

- **用户统计卡片**：总用户数、今日新增、本周新增、已绑定手机比例
- **注册趋势图**：近 7 天用户注册折线图

### 2. 用户管理

路径：`/admin/users`

#### 表格功能

| 列名 | 说明 | 特性 |
|------|------|------|
| ID | 用户 ID | 可排序、可切换显示 |
| 头像 | 用户头像 | 圆形显示，默认随机头像 |
| 昵称 | 用户昵称 | 可搜索、可切换 |
| 手机号 | 绑定手机号 | 可搜索、可复制 |
| 性别 | 0未知/1男/2女 | 可切换 |
| 小程序用户 | 是否有 openid | 图标显示 |
| 注册时间 | 用户注册时间 | 可排序、可切换 |
| 更新时间 | 最后修改时间 | 默认隐藏 |

#### 筛选器

- **性别筛选**：未知/男/女
- **小程序用户**：是/否/全部
- **已绑定手机**：是/否/全部
- **注册时间范围**：选择开始/结束日期

#### 操作

- **创建用户**：手动添加用户（管理员或小程序用户）
- **编辑**：修改用户信息
- **删除**：删除用户（支持批量删除）

### 3. 导航分组

后台导航按功能分组：

- **系统管理**：用户管理等核心功能

## 开发指南

### 添加新资源

1. 创建 Resource 类：
```bash
php artisan make:filament-resource Order
```

2. 配置表单和表格：
```php
// app/Filament/Resources/OrderResource.php
public static function form(Schema $schema): Schema { ... }
public static function table(Table $table): Table { ... }
```

### 添加新小组件

1. 统计卡片：
```bash
php artisan make:filament-widget OrderStats --stats-overview
```

2. 图表组件：
```bash
php artisan make:filament-widget OrderChart --chart
```

### 修改主题

主题文件位于：`resources/css/filament/admin/theme.css`

修改后重新构建：
```bash
npm run build
```

### 本地化

后台已配置为中文（`zh_CN`），如需修改：

1. 编辑 `.env`：
```env
APP_LOCALE=zh_CN
```

2. 清除缓存：
```bash
php artisan optimize:clear
```

## 测试数据

填充演示数据：
```bash
php artisan db:seed
```

将创建：
- 1 个管理员账号
- 20 个测试小程序用户（含随机头像、昵称、手机号）

## 安全建议

1. **修改默认密码**：首次登录后立即修改
2. **生产环境限制**：配置 `canAccessPanel()` 方法限制访问权限
3. **HTTPS**：生产环境务必启用 HTTPS
4. **定期备份**：定期备份数据库

## 常见问题

### Q: 后台显示无样式？

A: 运行以下命令构建资源：
```bash
php artisan filament:assets
npm run build
```

### Q: 如何添加新的后台用户？

A: 使用 Artisan 命令：
```bash
php artisan make:filament-user
```

### Q: 如何自定义品牌名称？

A: 编辑 `app/Providers/Filament/AdminPanelProvider.php`：
```php
->brandName('你的品牌名')
```

## 相关文档

- [认证接口文档](./auth.md)
- [数据库设计](./database.md)
- [项目结构](./structure.md)
- [测试指南](./testing.md)
