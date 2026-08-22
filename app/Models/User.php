<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable([
    'openid', 'unionid', 'nickname', 'avatar', 'gender', 'phone', 'meta',
    'name', 'email', 'password', 'status', 'ban_reason',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /**
     * 账户状态：默认正常。
     */
    public const STATUS_NORMAL = 'normal';

    public const STATUS_BANNED = 'banned';
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 控制用户能否访问 Filament 后台面板
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // 必须拥有 email 与密码（即后台账号）且至少绑定一个 admin 角色。
        // 首次部署（roles 表为空）视为安装阶段，沿用旧的「email+password」放行，避免锁死。
        $hasCredential = $this->email !== null && $this->password !== null;
        if (! $hasCredential) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('roles')
            || \App\Models\Role::query()->doesntExist()) {
            return true;
        }

        return $this->hasRole(['super-admin', 'admin']);
    }

    /**
     * 角色的关联（多对多）
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * 是否拥有给定角色（slug 或名称，可传数组任一匹配）
     */
    public function hasRole(string|array $slugs): bool
    {
        $slugs = (array) $slugs;

        return $this->roles()
            ->whereIn('slug', $slugs)
            ->exists();
    }

    /**
     * 是否为超级管理员（可越过资源级权限限制）
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * 获取用户所有权限标识（聚合所有角色的菜单权限）
     */
    public function getPermissionsAttribute(): array
    {
        return app(\App\Support\MenuPermissionManager::class)->getUserPermissions($this);
    }

    /**
     * 获取用户所有菜单 slug
     */
    public function getMenuSlugsAttribute(): array
    {
        return app(\App\Support\MenuPermissionManager::class)->getUserMenuSlugs($this);
    }

    /**
     * 指派角色（按 slug）。
     * 仅添加角色，不清除已有角色；变更后立即清除权限缓存。
     */
    public function assignRole(string $slug): void
    {
        $role = Role::where('slug', $slug)->first();

        if (! $role) {
            return;
        }

        $this->roles()->syncWithoutDetaching([$role->id]);

        app(\App\Support\MenuPermissionManager::class)->clearUserCache($this);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'meta' => 'array',
            'gender' => 'integer',
            'banned_at' => 'datetime',
        ];
    }

    /**
     * 用户的 API Token 关联
     */
    public function tokens()
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    /**
     * 是否已封禁
     */
    public function isBanned(): bool
    {
        return $this->status === self::STATUS_BANNED;
    }

    /**
     * 封禁用户：记录状态/时间/原因，并撤销其全部 API Token（立即踢下线）。
     */
    public function ban(string $reason = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_BANNED,
            'banned_at' => now(),
            'ban_reason' => $reason,
        ])->save();

        // 吊销全部登录态，防止封禁后仍持旧 token 调用接口
        $this->tokens()->delete();
    }

    /**
     * 解封用户：恢复正常状态。
     */
    public function unban(): void
    {
        $this->forceFill([
            'status' => self::STATUS_NORMAL,
            'banned_at' => null,
            'ban_reason' => null,
        ])->save();
    }

    /**
     * 作用域：仅正常用户
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NORMAL);
    }
}
