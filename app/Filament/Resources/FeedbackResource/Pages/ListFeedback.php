<?php

namespace App\Filament\Resources\FeedbackResource\Pages;

use App\Filament\Resources\FeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    public function getTitle(): string
    {
        return '用户反馈';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('onlyPending')
                ->label('仅看待处理')
                ->icon('heroicon-o-exclamation-circle')
                ->color('warning')
                ->action(function (): void {
                    $this->tableFilters['status'] = ['value' => \App\Models\Feedback::STATUS_PENDING];
                    $this->resetTable();
                }),
        ];
    }
}
