<?php

namespace App\Filament\Resources\SubscribeMessageFailureResource\Pages;

use App\Filament\Resources\SubscribeMessageFailureResource;
use App\Models\SubscribeMessageFailure;
use App\Services\WechatService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscribeMessageFailure extends ViewRecord
{
    protected static string $resource = SubscribeMessageFailureResource::class;

    public function getTitle(): string
    {
        return '失败记录详情';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resend')
                ->label('重发')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('确认重发')
                ->modalDescription('将重新调用微信接口推送此条订阅消息，成功后会标记为已解决；失败则更新错误信息。')
                ->action(function (SubscribeMessageFailure $record): void {
                    $this->resendInternal($record);
                    $this->refreshFormData([]);
                }),
            Action::make('markResolved')
                ->label('标记已解决')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('标记为已解决')
                ->form([
                    \Filament\Forms\Components\Textarea::make('resolved_note')
                        ->label('解决备注')
                        ->rows(3)
                        ->placeholder('例如：用户已取消订阅，无需再发'),
                ])
                ->action(function (SubscribeMessageFailure $record, array $data): void {
                    $record->update([
                        'resolved_at' => now(),
                        'resolved_note' => $data['resolved_note'] ?? '手动标记为已解决',
                    ]);

                    $this->refreshFormData([]);
                    Notification::make()
                        ->success()
                        ->title('已标记为已解决')
                        ->send();
                }),
        ];
    }

    protected function resendInternal(SubscribeMessageFailure $record): void
    {
        try {
            /** @var WechatService $wechatService */
            $wechatService = app(WechatService::class);

            $payload = $record->payload ?? [];
            $data = $payload['data'] ?? [];
            $page = $record->page ?? ($payload['page'] ?? null);
            $options = $payload['options'] ?? [];

            $result = $wechatService->sendSubscribeMessage(
                openid: $record->openid,
                templateId: $record->template_id,
                data: $data,
                page: $page,
                options: $options,
            );

            if ($result['success']) {
                $record->update([
                    'resolved_at' => now(),
                    'resolved_note' => '查看页手动重发成功',
                    'attempts' => $record->attempts + 1,
                    'last_errcode' => 0,
                    'last_errmsg' => 'ok',
                    'last_attempted_at' => now(),
                ]);

                Notification::make()
                    ->success()
                    ->title('重发成功')
                    ->send();
            } else {
                $record->update([
                    'attempts' => $record->attempts + 1,
                    'last_errcode' => $result['errcode'],
                    'last_errmsg' => $result['errmsg'],
                    'last_attempted_at' => now(),
                ]);

                Notification::make()
                    ->danger()
                    ->title('重发失败：' . ($result['errmsg'] ?? '未知错误'))
                    ->send();
            }
        } catch (\Throwable $e) {
            $record->update([
                'attempts' => $record->attempts + 1,
                'last_errcode' => -999,
                'last_errmsg' => '重发异常：' . $e->getMessage(),
                'last_attempted_at' => now(),
            ]);

            Notification::make()
                ->danger()
                ->title('重发异常：' . $e->getMessage())
                ->send();
        }
    }
}
