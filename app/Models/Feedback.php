<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用户反馈。
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $type  suggestion|bug|complaint|other
 * @property string $content
 * @property string|null $contact
 * @property string $status  pending|processing|resolved|rejected
 * @property string|null $handle_note
 * @property int|null $handled_by
 * @property \Illuminate\Support\Carbon|null $handled_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'content', 'contact',
        'status', 'handle_note', 'handled_by', 'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public const TYPE_SUGGESTION = 'suggestion';
    public const TYPE_BUG = 'bug';
    public const TYPE_COMPLAINT = 'complaint';
    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
