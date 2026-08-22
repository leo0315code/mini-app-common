<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
        'route',
        'permission',
        'sort_order',
        'is_visible',
        'is_active',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

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
}
