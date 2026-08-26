<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 首页运营位（Banner）。后台维护，小程序端拉取生效中的列表。
 *
 * @property int $id
 * @property string $title
 * @property string $image
 * @property string $link_type  none|article|url
 * @property int|null $article_id
 * @property string|null $url
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 */
#[Fillable([
    'title', 'image', 'link_type', 'article_id', 'url',
    'sort_order', 'starts_at', 'ends_at', 'is_active',
])]
class Banner extends Model
{
    use HasFactory;

    public const LINK_NONE = 'none';

    public const LINK_ARTICLE = 'article';

    public const LINK_URL = 'url';

    protected function casts(): array
    {
        return [
            'article_id' => 'integer',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * 小程序端生效条件：已启用且在生效时间窗口内。
     */
    public function scopeActive($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            });
    }

    /**
     * 图片完整访问地址（public 磁盘）。
     */
    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image);
    }

    /**
     * 是否处于生效时间窗口内（不含启用开关，供后台提示用）。
     */
    public function withinWindow(): bool
    {
        $now = now();

        return ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->ends_at === null || $this->ends_at->gt($now));
    }
}
