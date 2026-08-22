<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 公告 / 通知。后台发布，小程序端拉取已发布内容。
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string $type  notice|activity|update
 * @property string $status  draft|published|offline
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon $created_at
 */
#[Fillable([
    'title', 'content', 'type', 'status', 'published_at', 'created_by',
])]
class Announcement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public const TYPE_NOTICE = 'notice';
    public const TYPE_ACTIVITY = 'activity';
    public const TYPE_UPDATE = 'update';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_OFFLINE = 'offline';

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 仅返回已发布且在发布时间之后的公告。
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }
}
