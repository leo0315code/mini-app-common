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
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable([
    'openid', 'unionid', 'nickname', 'avatar', 'gender', 'phone', 'meta',
    'name', 'email', 'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
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
     * 指派角色（按 slug）
     */
    public function assignRole(string $slug): void
    {
        if ($role = Role::where('slug', $slug)->first()) {
            $this->roles()->syncWithoutDetaching([$role->id]);
        }
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
        ];
    }

    /**
     * 用户的 API Token 关联
     */
    public function tokens()
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }
}
