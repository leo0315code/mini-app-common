<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    /**
     * 创建后若为已发布状态，则触发微信订阅消息推送。
     */
    protected function afterCreate(): void
    {
        /** @var \App\Models\Announcement $record */
        $record = $this->getRecord();
        if ($record->status === \App\Models\Announcement::STATUS_PUBLISHED) {
            try {
                app(\App\Services\SubscribeMessageService::class)->pushAnnouncementPublished($record);
            } catch (\Throwable $e) {
                // 忽略推送异常，业务流程不受影响
            }
        }
    }
}
