<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = '用户管理';

    protected static ?string $modelLabel = '用户';

    protected static ?string $pluralModelLabel = '用户列表';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基本信息')
                    ->schema([
                        Forms\Components\TextInput::make('openid')
                            ->label('OpenID')
                            ->maxLength(64)
                            ->disabled(fn (?User $record) => $record !== null),
                        Forms\Components\TextInput::make('unionid')
                            ->label('UnionID')
                            ->maxLength(64),
                        Forms\Components\TextInput::make('nickname')
                            ->label('昵称')
                            ->maxLength(64),
                        Forms\Components\TextInput::make('phone')
                            ->label('手机号')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\Select::make('gender')
                            ->label('性别')
                            ->options([
                                0 => '未知',
                                1 => '男',
                                2 => '女',
                            ])
                            ->default(0),
                        Forms\Components\TextInput::make('avatar')
                            ->label('头像')
                            ->url()
                            ->maxLength(512),
                    ])->columns(2),

                Section::make('扩展信息')
                    ->components([
                        Forms\Components\KeyValue::make('meta')
                            ->label('Meta 数据')
                            ->keyLabel('键')
                            ->valueLabel('值'),
                    ]),

                Section::make('后台角色')
                    ->description('勾选该用户拥有的后台角色（RBAC）。超级管理员可访问所有功能。')
                    ->schema([
                        Forms\Components\CheckboxList::make('roles')
                            ->label('角色')
                            ->relationship('roles', 'name')
                            ->preload()
                            ->columns(2),
                    ])
                    ->collapsible(),
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
                ImageColumn::make('avatar')
                    ->label('头像')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=User&background=random'),
                TextColumn::make('nickname')
                    ->label('昵称')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('手机号')
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->copyMessage('手机号已复制'),
                TextColumn::make('gender')
                    ->label('性别')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        '0' => '未知',
                        '1' => '男',
                        '2' => '女',
                        default => $state,
                    })
                    ->toggleable(),
                IconColumn::make('openid')
                    ->label('小程序用户')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->label('注册时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')
                    ->label('角色')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === User::STATUS_BANNED ? '已封禁' : '正常')
                    ->color(fn (string $state): string => $state === User::STATUS_BANNED ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('banned_at')
                    ->label('封禁时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        User::STATUS_NORMAL => '正常',
                        User::STATUS_BANNED => '已封禁',
                    ]),
                SelectFilter::make('gender')
                    ->label('性别')
                    ->options([
                        0 => '未知',
                        1 => '男',
                        2 => '女',
                    ]),
                TernaryFilter::make('openid')
                    ->label('小程序用户')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('openid'),
                        false: fn (Builder $query) => $query->whereNull('openid'),
                        blank: fn (Builder $query) => $query,
                    ),
                TernaryFilter::make('phone')
                    ->label('已绑定手机')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('phone')->where('phone', '!=' , ''),
                        false: fn (Builder $query) => $query->whereNull('phone')->orWhere('phone', '=' , ''),
                        blank: fn (Builder $query) => $query,
                    ),
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('注册开始日期'),
                        Forms\Components\DatePicker::make('created_until')->label('注册结束日期'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('ban')
                    ->label('封禁')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->status !== User::STATUS_BANNED)
                    ->form([
                        Forms\Components\Textarea::make('ban_reason')
                            ->label('封禁原因')
                            ->rows(2),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->ban($data['ban_reason'] ?? null);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('已封禁该用户')
                            ->send();
                    }),
                Action::make('unban')
                    ->label('解封')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->status === User::STATUS_BANNED)
                    ->action(function (User $record): void {
                        $record->unban();
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('已解封该用户')
                            ->send();
                    }),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
