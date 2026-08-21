<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNotification extends EditRecord
{
    protected static string $resource = NotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * 保存后若为已发布且尚未展开接收人（首次发布），则按 scope 展开。
     * 已存在回执说明之前已发布，避免重复派发。
     */
    protected function afterSave(): void
    {
        /** @var \App\Models\Notification $record */
        $record = $this->getRecord();
        if ($record->published && ! $record->recipients()->exists()) {
            $record->dispatchToRecipients();
        }
    }
}
