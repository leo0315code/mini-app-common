# RBAC 权限系统

本文档说明后台角色管理、菜单权限分配、级联选择、策略守卫、缓存失效等完整 RBAC 实现。

---

## 1. 系统架构

```
┌───────────────────────────────────────────────────────────┐
│                      用户 (User)                           │
│  ┌─────────────────────────────────────────────────────┐  │
│  │   roles() ─── M:N ─── Role ─── M:N ─── Menu         │  │
│  └─────────────────────────────────────────────────────┘  │
│                        │                          │        │
│                        ▼                          ▼        │
│              MenuPermissionManager            permission  │
│              (统一权限查询 + 缓存)              (菜单上的权限键)
│                        │                                 │
│           ┌────────────┼────────────┐                    │
│           ▼            ▼            ▼                    │
│        Policy      Middleware    Filament               │
│     (资源级守卫)   (API 保护)   (侧栏/资源可见性)          │
└───────────────────────────────────────────────────────────┘
```

### 核心概念

| 概念 | 说明 |
|------|------|
| **角色 (Role)** | 权限集合体，一个用户可拥有多个角色 |
| **菜单 (Menu)** | 后台导航项 + 权限载体，每个菜单携带 `permission` 字段（如 `article.view`） |
| **权限 (Permission)** | 形如 `{资源}.{操作}` 的字符串，如 `article.view`、`user.manage` |
| **策略 (Policy)** | Laravel Policy，基于菜单权限判断用户能否访问资源 |
| **级联选择** | 勾选父菜单自动选中所有子菜单 |

### 权限键规范

```
{resource}.{action}
  │         │
  │         └── view / manage
  └── article / category / user / role / menu / feedback
      announcement / audit-log / media / dashboard / settings
```

- `*.view` — 查看权限（列表/详情）
- `*.manage` — 管理权限（创建/编辑/删除）

---

## 2. 数据表结构

### roles 表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| name | string(50) | 角色名称 |
| slug | string(50) | 角色标识（唯一） |
| description | text nullable | 角色说明 |
| created_at / updated_at | timestamp | 时间戳 |

### menus 表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| parent_id | bigint nullable | 父菜单 ID（树形结构） |
| name | string(50) | 菜单显示名 |
| slug | string(50) | 菜单标识（唯一） |
| icon | string(50) nullable | Filament 图标名 |
| route | string(255) nullable | Filament 路由 |
| permission | string(100) nullable | 权限键 |
| sort_order | integer | 排序 |
| is_visible | boolean | 侧栏是否显示 |
| is_active | boolean | 是否启用 |

### 中间表

| 表 | 关联 | 说明 |
|----|------|------|
| role_user | role_id + user_id | 用户-角色多对多 |
| menu_role | menu_id + role_id | 角色-菜单多对多 |

---

## 3. 权限查询流程

### 3.1 MenuPermissionManager

统一的权限查询入口，带缓存（TTL 3600 秒）：

```php
// 获取用户所有权限
$manager = app(MenuPermissionManager::class);
$permissions = $manager->getUserPermissions($user);
// 返回: ['article.view', 'article.manage', 'category.view', ...]

// 检查用户是否拥有某权限
$has = $manager->hasPermission($user, 'article.view'); // bool

// 检查用户是否拥有任一权限
$has = $manager->hasAnyPermission($user, ['article.view', 'article.manage']); // bool
```

**特殊处理**：
- `super-admin` 角色自动返回 `['*']`（所有权限）
- 系统未初始化（roles/menus 表不存在）时返回 `['*']`
- 用户无角色时返回 `['*']`（兼容旧系统）

### 3.2 Policy 策略守卫

所有资源策略继承 `BasePolicy`：

```php
class ArticlePolicy extends BasePolicy
{
    protected string $permissionPrefix = 'article';
    // viewAny → article.view 或 article.manage
    // view   → article.view 或 article.manage
    // create → article.manage
    // update → article.manage
    // delete → article.manage
}
```

已实现的策略：
- `ArticlePolicy`、`CategoryPolicy`、`AnnouncementPolicy`
- `FeedbackPolicy`、`NotificationPolicy`、`MediaPolicy`
- `UserPolicy`、`RolePolicy`、`MenuPolicy`
- `SettingPolicy`、`AuditLogPolicy`

### 3.3 侧边栏权限过滤

Filament v5 使用 Policy-based 可见性：

- 资源的 `canViewAny()` 返回 false → 侧栏隐藏该资源
- 资源的 `canCreate()` 返回 false → 隐藏创建按钮
- 资源的 `canEdit()` 返回 false → 隐藏编辑按钮
- 资源的 `canDelete()` 返回 false → 隐藏删除按钮

---

## 4. 角色管理

### 4.1 创建角色

路径：`/admin/roles`

1. 填写角色名称、标识（slug）、说明
2. 选择预设模板（可选）：
   - **内容编辑** — article + category + feedback 管理
   - **运营** — 内容编辑 + 公告 + 用户查看
   - **审核员** — 只读审核权限
   - **系统管理员** — 除超级管理员外的最高权限
3. 分配菜单权限（树形结构）

### 4.2 菜单权限分配界面

树形结构交互：
- **父级勾选** → 自动选中所有子菜单
- **父级取消** → 自动取消所有子菜单
- **部分子项选中** → 父级显示「不确定」状态（indeterminate）
- **所有子项选中** → 自动选中父级

界面元素：
- 顶部统计卡片：总菜单数 / 已选数 / 进度条
- 工具栏：全选 / 清空 / 展开 / 折叠
- 层级缩进 + 图标 + 子项选中数徽章
- 保存后立即生效（缓存自动清除）

### 4.3 克隆角色

角色列表支持：
- **单个克隆** — 复制角色及其菜单权限
- **批量克隆** — 选中多个角色批量复制

### 4.4 预设模板

`RolePresetTemplates` 提供 4 种预设：

```php
// 应用预设到已有角色
RolePresetTemplates::applyPreset($role, 'content_editor');

// 从预设创建新角色
$role = RolePresetTemplates::createFromPreset('operator', [
    'name' => '自定义运营',
    'slug' => 'custom-operator',
]);
```

---

## 5. 缓存机制

### 5.1 缓存键

```
user_permissions_{user_id}
```

TTL: 3600 秒（1 小时）

### 5.2 缓存失效

**三重保障**确保权限变更后立即生效：

| 触发场景 | 清除方式 |
|----------|----------|
| 角色创建/删除 | `PermissionCacheObserver::created/deleted` → `clearAllCache()` |
| 角色权限变更 (sync) | `PermissionCacheObserver::synced` → `clearAllCache()` |
| 角色分配/移除 | `PermissionCacheObserver::attached/detached` → `clearUserCache()` |
| 菜单创建 | `Menu::created` 事件 → `clearAllCache()` |
| 菜单删除 | `Menu::deleting` 事件 → `clearAllCache()` |
| 用户角色变更 | `User::assignRole()` 显式调用 `clearUserCache()` |
| 预设模板应用 | `RolePresetTemplates::applyPreset()` → `clearUserCache()` |

### 5.3 PermissionCacheObserver

```php
class PermissionCacheObserver
{
    public function created(object $model): void
    // Role / Menu → clearAllCache()

    public function updated(object $model): void
    // Role / Menu → clearAllCache()

    public function deleted(object $model): void
    // Role / Menu → clearAllCache()

    public function synced(object $model, string $relation): void
    // menu_role sync → clearAllCache()

    public function attached(object $model, string $relation, mixed $ids): void
    // role_user attach → clearUserCache()
}
```

### 5.4 手动清除

```php
// 清除指定用户缓存
app(MenuPermissionManager::class)->clearUserCache($user);

// 清除所有缓存
app(MenuPermissionManager::class)->clearAllCache();
```

---

## 6. API 权限中间件

### CheckMenuPermission

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'menu.permission'])
    ->prefix('admin')
    ->group(function () {
        // 管理员 API
    });
```

中间件行为：
- 从路由获取需要的权限（通过路由名称或注解）
- 查询当前用户权限列表
- 不匹配返回 `40303`（权限不足）

### 路由保护策略

```php
// 公开 API（无需认证）
Route::prefix('announcements')->group(function () {
    Route::get('/', [AnnouncementController::class, 'index']);
    Route::get('/{id}', [AnnouncementController::class, 'show']);
});

// 认证 API（需登录）
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/feedback', [FeedbackController::class, 'store']);
});

// 管理员 API（需权限）
Route::middleware(['auth:sanctum', 'menu.permission'])->prefix('admin')->group(function () {
    // 仅管理角色可访问
});
```

---

## 7. 审计日志

RBAC 相关操作自动记录审计：

### 监听事件

| 模型 | 事件 | 记录内容 |
|------|------|----------|
| Role | created/updated/deleted | 角色变更 |
| Menu | created/updated/deleted | 菜单变更 |
| Role-User | attached/detached/synced | 角色分配 |
| Role-Menu | attached/detached/synced | 权限分配 |

### 审计示例

```json
{
  "type": "updated",
  "module": "role",
  "description": "更新角色",
  "old_data": {"name": "编辑", "permissions": ["article.view"]},
  "new_data": {"name": "高级编辑", "permissions": ["article.view", "article.manage"]},
  "user_id": 1,
  "ip": "192.168.1.1"
}
```

---

## 8. 超级管理员

### 自动权限

`super-admin` 角色自动获取所有权限：

```php
public function isSuperAdmin(): bool
{
    return $this->hasRole('super-admin');
}

// MenuPermissionManager
if ($user->isSuperAdmin()) {
    return ['*'];
}
```

### 菜单自动分配

新增菜单时自动分配给 `super-admin` 角色：

```php
// Menu::created 事件
static::created(function (self $menu) {
    $superAdminRoles = Role::where('slug', 'super-admin')->get();
    foreach ($superAdminRoles as $role) {
        $role->menus()->syncWithoutDetaching([$menu->id]);
    }
    app(MenuPermissionManager::class)->clearAllCache();
});
```

### canAccessPanel 判定

```php
// User 模型
public function canAccessPanel(Panel $panel): bool
{
    // 必须同时具备 email + password
    if (empty($this->email) || empty($this->password)) {
        return false;
    }

    // 超级管理员直接放行
    if ($this->isSuperAdmin()) {
        return true;
    }

    // 其他角色需通过 Policy 检查
    return true; // 有 email+password 即可进后台
}
```

---

## 9. 开发指南

### 添加新资源权限

1. **创建菜单**（`MenusTableSeeder` 或后台菜单管理）：
```php
Menu::create([
    'name' => '新资源',
    'slug' => 'newresource',
    'permission' => 'newresource.view',
    ...
]);
Menu::create([
    'parent_id' => $parent->id,
    'name' => '新资源管理',
    'slug' => 'newresource.manage',
    'permission' => 'newresource.manage',
    ...
]);
```

2. **创建策略**：
```php
class NewResourcePolicy extends BasePolicy
{
    protected string $permissionPrefix = 'newresource';
}
```

3. **注册策略**（`AppServiceProvider`）：
```php
protected $policies = [
    NewResource::class => NewResourcePolicy::class,
];
```

4. **添加预设模板**（可选）：
```php
'newresource_manager' => [
    'permissions' => ['newresource.view', 'newresource.manage'],
],
```

### 权限检查方法

```php
// 在 Controller 中
$this->authorize('viewAny', NewResource::class);
$this->authorize('create', NewResource::class);
$this->authorize('update', $newResource);

// 手动检查
app(MenuPermissionManager::class)->hasPermission($user, 'newresource.view');
```

---

## 10. 常见问题

### Q: 新增菜单后现有角色看不到？

A: 菜单创建后 `super-admin` 会自动获得权限。其他角色需要手动在角色管理页面勾选新菜单。

### Q: 修改角色权限后不立即生效？

A: `PermissionCacheObserver` 会在权限变更时自动清除缓存。如果遇到延迟，可手动执行：
```bash
php artisan cache:clear
```

### Q: 如何查看某用户的所有权限？

```php
$permissions = app(MenuPermissionManager::class)->getUserPermissions($user);
```

### Q: 系统初始部署（无 roles/menus 表）怎么办？

A: `MenuPermissionManager` 检测到表不存在时自动返回 `['*']`（全部放行），确保系统可以先使用后授权。

### 相关文档

- [管理后台使用指南](./admin.md)
- [数据库设计](./database.md)
- [目录结构与核心文件](./structure.md)
- [测试指南](./testing.md)
