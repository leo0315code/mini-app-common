<?php

namespace App\Models;

use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 文章 / 内容（CMS）。后台富文本撰写，小程序端按频道拉取。
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $title
 * @property string|null $slug
 * @property string|null $cover
 * @property string|null $summary
 * @property string $content
 * @property string $status  draft|published|offline
 * @property bool $is_top
 * @property int $views
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Fillable([
    'category_id', 'title', 'slug', 'cover', 'summary', 'content',
    'status', 'is_top', 'views', 'created_by', 'published_at',
])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_top' => 'boolean',
            'views' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_OFFLINE = 'offline';

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 已发布且在发布时间之后的文章。
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * 列表默认排序：置顶优先 → 发布时间倒序。
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_top')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }
}
