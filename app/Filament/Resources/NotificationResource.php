<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use App\Models\User;
use App\Support\ExportsCsv;
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

class NotificationResource extends Resource
{
    use ExportsCsv;
    protected static ?string $model = Notification::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static string|\UnitEnum|null $navigationGroup = '内容运营';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = '站内通知';

    protected static ?string $modelLabel = '通知';

    protected static ?string $pluralModelLabel = '通知列表';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() ?: null;
    }

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
                                'system' => '系统通知',
                                'activity' => '活动',
                                'version' => '版本更新',
                            ])
                            ->default('system')
                            ->required(),
                        Forms\Components\Select::make('scope')
                            ->label('接收范围')
                            ->options([
                                'all' => '全部用户',
                                'registered' => '已注册小程序用户',
                                'specified' => '指定用户',
                            ])
                            ->default('all')
                            ->required()
                            ->live(),
                    ])->columns(2),
                Section::make('接收人')
                    ->description('当接收范围为「指定用户」时选择具体接收人。')
                    ->schema([
                        Forms\Components\Select::make('targets')
                            ->label('指定用户')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn () => User::query()->pluck('nickname', 'id')->filter()->all())
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('scope') === 'specified'),
                    ])
                    ->collapsible(),
                Section::make('正文')
                    ->schema([
                        Forms\Components\Textarea::make('body')
                            ->label('正文')
                            ->required()
                            ->rows(4)
                            ->maxLength(2000),
                    ]),
                Section::make('发布')
                    ->schema([
                        Forms\Components\Toggle::make('published')
                            ->label('立即发布')
                            ->default(true),
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
                        'activity' => '活动',
                        'version' => '版本更新',
                        default => '系统通知',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'activity' => 'success',
                        'version' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('scope')
                    ->label('范围')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'registered' => '已注册',
                        'specified' => '指定用户',
                        default => '全部',
                    }),
                TextColumn::make('recipients_count')
                    ->label('接收人数')
                    ->counts('recipients')
                    ->sortable(),
                TextColumn::make('read_rate')
                    ->label('已读率')
                    ->state(fn (Notification $record): string => (function () use ($record) {
                        $total = $record->recipients()->count();
                        if ($total === 0) {
                            return '—';
                        }
                        $read = $record->recipients()->wherePivot('read', true)->count();

                        return round($read / $total * 100) . '%';
                    })())
                    ->badge()
                    ->color(fn (string $state): string => $state === '—' ? 'gray' : (str_starts_with($state, '100') ? 'success' : 'info')),
                IconColumn::make('published')
                    ->label('已发布')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('creator.name')
                    ->label('发送人')
                    ->formatStateUsing(fn ($state, $record) => $record->creator?->name ?? '—'),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        'system' => '系统通知',
                        'activity' => '活动',
                        'version' => '版本更新',
                    ]),
                SelectFilter::make('scope')
                    ->label('范围')
                    ->options([
                        'all' => '全部',
                        'registered' => '已注册',
                        'specified' => '指定用户',
                    ]),
                TernaryFilter::make('published')
                    ->label('已发布')
                    ->nullable(),
                SelectFilter::make('user')
                    ->label('接收用户')
                    ->options(User::pluck('nickname', 'id')->filter()->toArray())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q, $v) => $q->whereHas('recipients', fn (Builder $q) => $q->where('user_id', $v)))),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                self::buildExportAllHeaderAction(
                    baseQuery: Notification::query()->with(['creator'])->withCount('recipients'),
                    columnMap: [
                        'id' => 'ID',
                        'title' => '标题',
                        'type_txt' => '类型',
                        'scope_txt' => '接收范围',
                        'body' => '正文',
                        'published_txt' => '发布状态',
                        'recipients_count' => '接收人数',
                        'read_rate' => '已读率',
                        'creator_name' => '发送人',
                        'created_at_txt' => '创建时间',
                        'published_at_txt' => '发布时间',
                    ],
                    label: '导出全部通知',
                    fileNamePrefix: 'notifications',
                    rowCallback: static fn (Notification $n): array => [
                        $n->id,
                        (string) $n->title,
                        match ((string) $n->type) {
                            'activity' => '活动',
                            'version' => '版本更新',
                            default => '系统通知',
                        },
                        match ((string) $n->scope) {
                            'registered' => '已注册',
                            'specified' => '指定用户',
                            default => '全部',
                        },
                        (string) $n->body,
                        $n->published ? '已发布' : '草稿',
                        (int) ($n->recipients_count ?? $n->recipients()->count()),
                        (function () use ($n): string {
                            $total = (int) ($n->recipients_count ?? $n->recipients()->count());
                            if ($total === 0) {
                                return '0%';
                            }
                            $read = $n->recipients()->wherePivot('read', true)->count();

                            return round($read / $total * 100) . '%';
                        })(),
                        $n->creator?->name ?? '—',
                        $n->created_at?->format('Y-m-d H:i:s') ?? '',
                        $n->published_at?->format('Y-m-d H:i:s') ?? '',
                    ],
                ),
                BulkActionGroup::make([
                    self::buildExportSelectedBulkAction(
                        columnMap: [
                            'id' => 'ID',
                            'title' => '标题',
                            'type_txt' => '类型',
                            'scope_txt' => '接收范围',
                            'published_txt' => '发布状态',
                            'recipients_count' => '接收人数',
                            'created_at_txt' => '创建时间',
                        ],
                        label: '导出所选 CSV',
                        fileNamePrefix: 'notifications',
                        rowCallback: static fn (Notification $n): array => [
                            $n->id,
                            (string) $n->title,
                            match ((string) $n->type) {
                                'activity' => '活动',
                                'version' => '版本更新',
                                default => '系统通知',
                            },
                            match ((string) $n->scope) {
                                'registered' => '已注册',
                                'specified' => '指定用户',
                                default => '全部',
                            },
                            $n->published ? '已发布' : '草稿',
                            $n->recipients()->count(),
                            $n->created_at?->format('Y-m-d H:i:s') ?? '',
                        ],
                    ),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn (Notification $record): ?string => $record->published ? null : 'fi-ta-row-unpublished')
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'create' => Pages\CreateNotification::route('/create'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }

    /**
     * 创建时记录操作人与发布时间。
     */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creator_id'] = auth()->id();
        $data['published_at'] = ($data['published'] ?? true) ? now() : null;

        return $data;
    }

    /**
     * 编辑后同步发布状态。
     */
    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['published'] ?? false) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
