<?php

namespace App\Filament\Resources\SubscribeMessageFailureResource\Pages;

use App\Filament\Resources\SubscribeMessageFailureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscribeMessageFailures extends ListRecords
{
    protected static string $resource = SubscribeMessageFailureResource::class;

    public function getTitle(): string
    {
        return '推送失败记录';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('onlyUnresolved')
                ->label('仅看待处理')
                ->icon('heroicon-o-exclamation-circle')
                ->color('danger')
                ->action(function (): void {
                    $this->tableFilters['resolved'] = ['value' => false];
                    $this->resetTable();
                }),
        ];
    }
}
