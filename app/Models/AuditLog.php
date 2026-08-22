<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 操作审计日志。
 *
 * @property int $id
 * @property string $type
 * @property string $module
 * @property string|null $action
 * @property string|null $description
 * @property array|null $old_data
 * @property array|null $new_data
 * @property int|null $user_id
 * @property string|null $url
 * @property string|null $ip
 * @property \Illuminate\Support\Carbon $created_at
 */
#[Fillable([
    'type', 'module', 'action', 'description',
    'old_data', 'new_data', 'subject_type', 'subject_id',
    'user_id', 'url', 'ip',
])]
class AuditLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'old_data' => 'array',
            'new_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
