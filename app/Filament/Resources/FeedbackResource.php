<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeedbackResource\Pages;
use App\Models\Feedback;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = '内容运营';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = '用户反馈';

    protected static ?string $modelLabel = '反馈';

    protected static ?string $pluralModelLabel = '反馈列表';

    public static function getNavigationBadge(): ?string
    {
        $pending = Feedback::where('status', Feedback::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function canCreate(): bool
    {
        return false; // 反馈由小程序端提交，后台只处理
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('反馈信息')
                ->schema([
                    Forms\Components\TextInput::make('user.nickname')->label('用户')->disabled(),
                    Forms\Components\TextInput::make('type')->label('类型')->disabled(),
                    Forms\Components\Textarea::make('content')->label('内容')->disabled()->rows(4),
                    Forms\Components\TextInput::make('contact')->label('联系方式')->disabled(),
                    Forms\Components\TextInput::make('status')->label('状态')->disabled(),
                    Forms\Components\TextInput::make('created_at')->label('提交时间')->disabled(),
                ])->columns(2),
            Section::make('处理')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('处理结果')
                        ->options([
                            Feedback::STATUS_PENDING => '待处理',
                            Feedback::STATUS_PROCESSING => '处理中',
                            Feedback::STATUS_RESOLVED => '已解决',
                            Feedback::STATUS_REJECTED => '已驳回',
                        ])
                        ->required(),
                    Forms\Components\Textarea::make('handle_note')
                        ->label('处理备注 / 回复内容')
                        ->rows(4)
                        ->helperText('将记录在反馈详情，也可作为对用户回复的内容'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('user.nickname')
                    ->label('用户')
                    ->formatStateUsing(fn ($state, $record) => $record->user?->nickname ?? '游客')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Feedback::TYPE_SUGGESTION => '建议',
                        Feedback::TYPE_BUG => '缺陷',
                        Feedback::TYPE_COMPLAINT => '投诉',
                        Feedback::TYPE_OTHER => '其他',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'bug' => 'danger',
                        'complaint' => 'warning',
                        'suggestion' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('content')
                    ->label('内容')
                    ->limit(40)
                    ->tooltip(fn (Feedback $record) => $record->content),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Feedback::STATUS_PENDING => '待处理',
                        Feedback::STATUS_PROCESSING => '处理中',
                        Feedback::STATUS_RESOLVED => '已解决',
                        Feedback::STATUS_REJECTED => '已驳回',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'resolved' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('handler.name')
                    ->label('处理人')
                    ->formatStateUsing(fn ($state, $record) => $record->handler?->name ?? '—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('提交时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        Feedback::TYPE_SUGGESTION => '建议',
                        Feedback::TYPE_BUG => '缺陷',
                        Feedback::TYPE_COMPLAINT => '投诉',
                        Feedback::TYPE_OTHER => '其他',
                    ]),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        Feedback::STATUS_PENDING => '待处理',
                        Feedback::STATUS_PROCESSING => '处理中',
                        Feedback::STATUS_RESOLVED => '已解决',
                        Feedback::STATUS_REJECTED => '已驳回',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('handle')
                    ->label('处理')
                    ->icon('heroicon-o-check')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('处理结果')
                            ->options([
                                Feedback::STATUS_PROCESSING => '处理中',
                                Feedback::STATUS_RESOLVED => '已解决',
                                Feedback::STATUS_REJECTED => '已驳回',
                            ])
                            ->default(Feedback::STATUS_RESOLVED)
                            ->required(),
                        Forms\Components\Textarea::make('handle_note')
                            ->label('处理备注 / 回复内容')
                            ->rows(3),
                    ])
                    ->action(function (Feedback $record, array $data): void {
                        $record->update([
                            'status' => $data['status'],
                            'handle_note' => $data['handle_note'],
                            'handled_by' => auth()->id(),
                            'handled_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('反馈已处理')
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedback::route('/'),
            'view' => Pages\ViewFeedback::route('/{record}'),
        ];
    }
}
