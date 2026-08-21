<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use App\Models\Media;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    /**
     * 处理上传文件：FileUpload 返回磁盘相对路径数组，
     * 这里落库为 media 记录（取第一个）。
     */
    protected function handleRecordCreation(array $data): Media
    {
        $path = $data['upload'] ?? null;
        if (is_array($path)) {
            $path = $path[0] ?? null;
        }

        $record = new Media();
        if ($path) {
            $fileName = basename($path);
            $record->fill([
                'user_id' => auth()->id(),
                'collection' => Media::inferCollectionFromFileName($fileName),
                'file_name' => $fileName,
                'path' => $path,
                'disk' => 'public',
                'mime_type' => Storage::disk('public')->mimeType($path),
                'url' => Storage::disk('public')->url($path),
                'size' => Storage::disk('public')->size($path),
            ]);
        }
        $record->save();

        return $record;
    }
}
