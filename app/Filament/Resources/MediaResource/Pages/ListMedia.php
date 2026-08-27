<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use App\Models\Media;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(function (array $data): Media {
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
                }),
        ];
    }
}
