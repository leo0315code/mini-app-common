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
            Actions\CreateAction::make(),
        ];
    }
}
