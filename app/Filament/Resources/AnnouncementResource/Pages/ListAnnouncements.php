<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    public function getTitle(): string
    {
        return '公告管理';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->requiresConfirmation()
                ->modalHeading('确认创建')
                ->modalDescription('若状态为「已发布」，创建后将向全体订阅用户推送微信通知。请确认内容无误后再创建。')
                ->after(function ($record): void {
                    /** @var \App\Models\Announcement $record */
                    if ($record->status === \App\Models\Announcement::STATUS_PUBLISHED) {
                        try {
                            app(\App\Services\SubscribeMessageService::class)->pushAnnouncementPublished($record);
                        } catch (\Throwable $e) {
                            // 忽略推送异常，业务流程不受影响
                        }
                    }
                }),
        ];
    }
}
