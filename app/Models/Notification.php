<?php

namespace App\Models;

use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'creator_id',
    'title',
    'body',
    'type',
    'scope',
    'targets',
    'published',
    'published_at',
])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'targets' => 'array',
            'published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * 接收人（带已读回执 pivot）
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_user')
            ->withPivot('read', 'read_at')
            ->withTimestamps();
    }

    /**
     * 发布后将通知按 scope 展开为接收人回执。
     */
    public function dispatchToRecipients(): void
    {
        $users = match ($this->scope) {
            'specified' => User::query()->whereIn('id', $this->targets ?? [])->pluck('id')->all(),
            'registered' => User::query()->whereNotNull('openid')->pluck('id')->all(),
            default => User::query()->pluck('id')->all(),
        };

        $this->recipients()->syncWithoutDetaching(
            collect($users)->mapWithKeys(fn ($id) => [
                $id => ['read' => false, 'read_at' => null],
            ])->all()
        );
    }
}
