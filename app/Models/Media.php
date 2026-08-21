<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'collection',
        'file_name',
        'path',
        'disk',
        'mime_type',
        'url',
        'size',
        'meta',
    ];

    protected $casts = [
        'size' => 'integer',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 是否为图片（用于后台预览）。
     */
    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * 返回完整可访问 URL。
     */
    public function getUrlAttribute($value): string
    {
        if (! empty($value)) {
            return $value;
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    protected static function booted(): void
    {
        static::deleting(function (Media $media) {
            if ($media->path && Storage::disk($media->disk)->exists($media->path)) {
                Storage::disk($media->disk)->delete($media->path);
            }
        });
    }
}
