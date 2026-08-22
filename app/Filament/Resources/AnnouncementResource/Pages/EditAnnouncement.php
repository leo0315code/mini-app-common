<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * 保存后若状态变为已发布，则触发微信订阅消息推送。
     * 只在状态从未发布变为发布时触发一次，通过判断 original 值避免重复推送。
     */
    protected function afterSave(): void
    {
        /** @var \App\Models\Announcement $record */
        $record = $this->getRecord();
        $originalStatus = $record->getOriginal('status');

        if (
            $record->status === \App\Models\Announcement::STATUS_PUBLISHED
            && $originalStatus !== \App\Models\Announcement::STATUS_PUBLISHED
        ) {
            try {
                app(\App\Services\SubscribeMessageService::class)->pushAnnouncementPublished($record);
            } catch (\Throwable $e) {
                // 忽略推送异常
            }
        }
    }
}
