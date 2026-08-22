<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait ManagesRichEditorAttachments
{
    /**
     * 返回一个 RichEditor 文件上传的保存回调：
     *  - 将文件移动到 disk/directory 指定路径；
     *  - 同步写一条 Media 记录（collection = rich-editor），
     *    user_id 取当前登录管理员，url 写 public 访问路径；
     *  - 返回存储路径供编辑器写入 HTML src。
     */
    protected static function richEditorSaveAttachmentCallback(string $disk = 'public'): \Closure
    {
        return function (TemporaryUploadedFile $file) use ($disk): string {
            $dir = 'rich-editor/'.now()->format('Ym');
            $path = $file->store($dir, $disk);

            if ($path === false) {
                throw new \RuntimeException('富文本文件上传失败，无法写入磁盘。');
            }

            // 富文本图片默认公开可见
            rescue(static function () use ($disk, $path): void {
                Storage::disk($disk)->setVisibility($path, 'public');
            }, report: false);

            try {
                Media::create([
                    'user_id' => auth()->id(),
                    'collection' => 'rich-editor',
                    'file_name' => $file->getClientOriginalName() ?: basename($path),
                    'path' => $path,
                    'disk' => $disk,
                    'mime_type' => rescue(static fn () => $file->getMimeType() ?: Storage::disk($disk)->mimeType($path)) ?: 'application/octet-stream',
                    'size' => $file->getSize() ?: Storage::disk($disk)->size($path),
                    'url' => Storage::disk($disk)->url($path),
                ]);
            } catch (\Throwable $e) {
                // 媒体表写入失败不影响编辑器实际落图，避免阻塞内容编辑
                report($e);
            }

            return $path;
        };
    }
}
