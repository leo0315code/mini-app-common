<?php

namespace App\Models;

use App\Support\MenuCascadeService;
use App\Support\MenuPermissionManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'parent_id',
    'name',
    'slug',
    'icon',
    'route',
    'permission',
    'sort_order',
    'is_visible',
    'is_active',
])]
class Menu extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (self $menu) {
            $superAdminRoles = Role::query()->where('slug', 'super-admin')->get();
            foreach ($superAdminRoles as $role) {
                $role->menus()->syncWithoutDetaching([$menu->id]);
            }

            app(MenuPermissionManager::class)->clearAllCache();
            app(MenuCascadeService::class)->clearCache();
        });

        static::deleting(function (self $menu) {
            $menu->children()->each(function ($child) {
                $child->delete();
            });

            $menu->roles()->detach();

            app(MenuPermissionManager::class)->clearAllCache();
            app(MenuCascadeService::class)->clearCache();
        });
    }

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort_order');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withTimestamps();
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getTree(): array
    {
        $menus = static::with(['children' => function ($q) {
            $q->orderBy('sort_order')->with(['children' => function ($q2) {
                $q2->orderBy('sort_order');
            }]);
        }])->root()->active()->get();

        return $menus->toArray();
    }

    public static function getFlatList(): \Illuminate\Support\Collection
    {
        return static::orderBy('sort_order')->active()->get()->mapWithKeys(function ($menu) {
            $label = $menu->name;
            if ($menu->parent) {
                $label = $menu->parent->name . ' / ' . $label;
            }
            return [$menu->id => $label];
        });
    }

    public function getDepthAttribute(): int
    {
        $depth = 0;
        $current = $this;
        while ($current->parent_id !== null) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    public function getLineageAttribute(): string
    {
        $parts = [];
        $current = $this;
        while ($current && $current->parent_id !== null) {
            $parts[] = $current->name;
            $current = $current->parent;
        }
        $parts[] = $current?->name ?? '';

        return implode(' / ', array_reverse($parts));
    }
}
