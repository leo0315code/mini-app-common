<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use App\Models\Notification;
use Filament\Actions;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\ListRecords;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markAllRead')
                ->label('全部标记已读')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $adminId = auth()->id();

                    $count = Notification::query()
                        ->where('published', true)
                        ->whereHas('recipients', fn ($q) => $q->where('user_id', $adminId)->where('read', false))
                        ->get()
                        ->each(fn (Notification $n) => $n->recipients()->updateExistingPivot($adminId, [
                            'read' => true,
                            'read_at' => now(),
                        ]))
                        ->count();

                    FilamentNotification::make()
                        ->success()
                        ->title($count > 0 ? "已将 {$count} 条通知标记为已读" : '没有未读通知')
                        ->send();
                }),
            Actions\CreateAction::make()
                ->requiresConfirmation()
                ->modalHeading('确认创建')
                ->modalDescription('若勾选「立即发布」，创建后将向所有接收人发送站内通知并推送微信订阅消息。请确认内容无误后再创建。')
                ->after(function ($record): void {
                    /** @var \App\Models\Notification $record */
                    if ($record->published) {
                        $record->dispatchToRecipients();
                        try {
                            app(\App\Services\SubscribeMessageService::class)->pushNotificationPublished($record);
                        } catch (\Throwable $e) {
                            // 忽略推送异常，业务流程不受影响
                        }
                    }
                }),
        ];
    }
}
