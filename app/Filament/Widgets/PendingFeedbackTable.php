<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FeedbackResource;
use App\Models\Feedback;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;

class PendingFeedbackTable extends BaseWidget
{
    protected static ?string $heading = '待处理反馈';

    protected static ?string $description = '最新的 pending 状态用户反馈，点击可进入处理';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Feedback::query()
            ->where('status', Feedback::STATUS_PENDING)
            ->latest()
            ->limit(10);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Feedback::TYPE_BUG => 'Bug',
                        Feedback::TYPE_COMPLAINT => '投诉',
                        Feedback::TYPE_SUGGESTION => '建议',
                        default => '其他',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Feedback::TYPE_BUG => 'danger',
                        Feedback::TYPE_COMPLAINT => 'warning',
                        Feedback::TYPE_SUGGESTION => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('content')
                    ->label('内容')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->content),
                TextColumn::make('contact')
                    ->label('联系方式')
                    ->default('—'),
                TextColumn::make('created_at')
                    ->label('提交时间')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->actions([
                Tables\Actions\Action::make('handle')
                    ->label('去处理')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(fn (Feedback $record): string => FeedbackResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
