<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotification extends CreateRecord
{
    protected static string $resource = NotificationResource::class;

    /**
     * 保存后若为已发布，则按 scope 展开接收人回执，并触发微信订阅消息推送。
     */
    protected function afterCreate(): void
    {
        /** @var \App\Models\Notification $record */
        $record = $this->getRecord();
        if ($record->published) {
            $record->dispatchToRecipients();

            // 触发微信订阅消息推送（静默失败，不阻塞业务）
            try {
                app(\App\Services\SubscribeMessageService::class)->pushNotificationPublished($record);
            } catch (\Throwable $e) {
                // 忽略推送异常，业务流程不受影响
            }
        }
    }
}
