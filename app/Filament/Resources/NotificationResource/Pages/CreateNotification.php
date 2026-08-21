<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotification extends CreateRecord
{
    protected static string $resource = NotificationResource::class;

    /**
     * 保存后若为已发布，则按 scope 展开接收人回执。
     */
    protected function afterCreate(): void
    {
        /** @var \App\Models\Notification $record */
        $record = $this->getRecord();
        if ($record->published) {
            $record->dispatchToRecipients();
        }
    }
}
