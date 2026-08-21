<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = '内容运营';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = '公告管理';

    protected static ?string $modelLabel = '公告';

    protected static ?string $pluralModelLabel = '公告列表';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基础信息')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('标题')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\Select::make('type')
                            ->label('类型')
                            ->options([
                                Announcement::TYPE_NOTICE => '通知',
                                Announcement::TYPE_ACTIVITY => '活动',
                                Announcement::TYPE_UPDATE => '版本更新',
                            ])
                            ->default(Announcement::TYPE_NOTICE)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                Announcement::STATUS_DRAFT => '草稿',
                                Announcement::STATUS_PUBLISHED => '已发布',
                                Announcement::STATUS_OFFLINE => '已下线',
                            ])
                            ->default(Announcement::STATUS_DRAFT)
                            ->required(),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('发布时间')
                            ->helperText('留空表示保存后立即按状态发布'),
                    ])->columns(2),
                Section::make('正文')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label('正文')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList', 'orderedList',
                                'link', 'blockquote', 'undo', 'redo',
                            ]),
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
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Announcement::TYPE_NOTICE => '通知',
                        Announcement::TYPE_ACTIVITY => '活动',
                        Announcement::TYPE_UPDATE => '版本更新',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'activity' => 'success',
                        'update' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Announcement::STATUS_DRAFT => '草稿',
                        Announcement::STATUS_PUBLISHED => '已发布',
                        Announcement::STATUS_OFFLINE => '已下线',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'offline' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('author.name')
                    ->label('发布人')
                    ->formatStateUsing(fn ($state, $record) => $record->author?->name ?? '—'),
                TextColumn::make('published_at')
                    ->label('发布时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        Announcement::TYPE_NOTICE => '通知',
                        Announcement::TYPE_ACTIVITY => '活动',
                        Announcement::TYPE_UPDATE => '版本更新',
                    ]),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        Announcement::STATUS_DRAFT => '草稿',
                        Announcement::STATUS_PUBLISHED => '已发布',
                        Announcement::STATUS_OFFLINE => '已下线',
                    ]),
                TernaryFilter::make('published')
                    ->label('仅已发布')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query) => $query->where('status', Announcement::STATUS_PUBLISHED),
                        false: fn (Builder $query) => $query->where('status', '!=', Announcement::STATUS_PUBLISHED),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }

    /**
     * 创建时记录操作人。
     */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (($data['status'] ?? null) === Announcement::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
