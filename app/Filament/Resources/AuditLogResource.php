<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{

    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = '操作日志';

    protected static ?string $modelLabel = '操作日志';

    protected static ?string $pluralModelLabel = '操作日志';

    protected static ?string $recordTitleAttribute = 'action';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $today = AuditLog::whereDate('created_at', today())->count();

        return $today > 0 ? (string) $today : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('module')->label('模块')->disabled(),
            Forms\Components\TextInput::make('action')->label('操作')->disabled(),
            Forms\Components\Textarea::make('description')->label('描述')->disabled(),
            Forms\Components\KeyValue::make('old_data')->label('变更前')->disabled(),
            Forms\Components\KeyValue::make('new_data')->label('变更后')->disabled(),
            Forms\Components\TextInput::make('url')->label('请求地址')->disabled(),
            Forms\Components\TextInput::make('ip')->label('IP')->disabled(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('操作信息')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('type')->label('类型')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'create' => '新增',
                        'update' => '修改',
                        'delete' => '删除',
                        'login' => '登录',
                        'config' => '配置',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'create' => 'success',
                        'update' => 'info',
                        'delete' => 'danger',
                        'login' => 'gray',
                        'config' => 'warning',
                        default => 'gray',
                    }),
                TextEntry::make('module')->label('模块'),
                TextEntry::make('action')->label('操作')->columnSpanFull(),
                TextEntry::make('user.name')->label('操作人')
                    ->formatStateUsing(fn ($state, $record) => $record->user?->name ?? $record->user?->nickname ?? '系统'),
                TextEntry::make('ip')->label('IP')->placeholder('—'),
                TextEntry::make('created_at')->label('时间')->dateTime('Y-m-d H:i:s'),
            ])->columns(2),
            Section::make('描述')->schema([
                TextEntry::make('description')->label('描述')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('数据变更')->schema([
                KeyValueEntry::make('old_data')->label('变更前')->placeholder('（无）'),
                KeyValueEntry::make('new_data')->label('变更后')->placeholder('（无）'),
            ])->columns(2),
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
                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'create' => 'success',
                        'update' => 'info',
                        'delete' => 'danger',
                        'login' => 'gray',
                        'config' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('module')
                    ->label('模块')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('操作')
                    ->limit(40)
                    ->searchable()
                    ->toggleable()
                    ->tooltip(fn ($record) => $record->action),
                TextColumn::make('user.name')
                    ->label('操作人')
                    ->formatStateUsing(fn ($state, $record) => $record->user?->name ?? $record->user?->nickname ?? '系统'),
                TextColumn::make('subject_type')
                    ->label('对象')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        'create' => '新增',
                        'update' => '修改',
                        'delete' => '删除',
                        'login' => '登录',
                        'config' => '配置',
                    ]),
                SelectFilter::make('module')
                    ->label('模块')
                    ->options([
                        'user' => '用户',
                        'token' => 'Token',
                        'announcement' => '公告',
                        'feedback' => '反馈',
                        'system' => '系统',
                    ]),
                SelectFilter::make('user')
                    ->label('操作人')
                    ->options(User::pluck('name', 'id')->filter()->toArray())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q, $v) => $q->where('user_id', $v))),
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('开始日期'),
                        Forms\Components\DatePicker::make('until')->label('结束日期'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->enhanceListExperience()
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
