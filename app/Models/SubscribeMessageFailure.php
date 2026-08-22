<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'job_uuid', 'scene', 'subject_type', 'subject_id',
    'openid', 'template_id', 'payload', 'page',
    'attempts', 'last_errcode', 'last_errmsg',
    'last_attempted_at', 'resolved_at', 'resolved_note',
])]
class SubscribeMessageFailure extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'last_attempted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
