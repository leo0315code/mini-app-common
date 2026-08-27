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
