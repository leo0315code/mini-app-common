<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\ExportsCsv;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserResource extends Resource
{

    use ExportsCsv;

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
                                0 => User::genderLabel(0),
                                1 => User::genderLabel(1),
                                2 => User::genderLabel(2),
                            ])
                            ->default(0),
                        Forms\Components\FileUpload::make('avatar')
                            ->label('头像')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios(['1:1'])
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText('上传圆形头像，留空则使用微信头像或默认占位图。')
                            // avatar 字段存的是「完整 URL 字符串」（微信同步来的远程 URL，
                            // 或本地上传后的 /storage/avatars/...），与 FileUpload 默认的相对路径模型不同，
                            // 故自定义存/取回调，保证落库与回显均为完整 URL：
                            ->saveUploadedFileUsing(function ($state): ?string {
                                if (blank($state)) {
                                    return null;
                                }
                                // 已为完整 URL（远程或上一次上传结果）则不重复处理
                                if (preg_match('#^(https?://|/)#', $state)) {
                                    return $state;
                                }
                                $path = $state;
                                $storage = \Illuminate\Support\Facades\Storage::disk('public');
                                if (! $storage->exists($path)) {
                                    return null;
                                }
                                return $storage->url($path);
                            })
                            ->getUploadedFileUsing(function ($file): ?array {
                                if (blank($file)) {
                                    return null;
                                }
                                // 完整 URL（远程或 /storage/...）直接回显，不走 disk->exists 判断
                                if (preg_match('#^(https?://|/)#', $file)) {
                                    return [
                                        'name' => basename($file),
                                        'size' => 0,
                                        'type' => 'image',
                                        'url' => $file,
                                    ];
                                }
                                $storage = \Illuminate\Support\Facades\Storage::disk('public');
                                if (! $storage->exists($file)) {
                                    return null;
                                }
                                return [
                                    'name' => basename($file),
                                    'size' => $storage->size($file),
                                    'type' => $storage->mimeType($file),
                                    'url' => $storage->url($file),
                                ];
                            }),
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
                            ->columns(2),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function infolistComponents(): array
    {
        return [
            Grid::make(2)
                ->schema([
                            Section::make('基本信息')
                                ->schema([
                                    TextEntry::make('id')->label('ID'),
                                    TextEntry::make('nickname')->label('昵称'),
                                    ImageEntry::make('avatar')->label('头像')
                                        ->size(64)
                                        ->circular()
                                        ->defaultImageUrl('https://ui-avatars.com/api/?name=User&background=random')
                                        ->placeholder('—'),
                                    TextEntry::make('gender')->label('性别')
                                        ->formatStateUsing(fn (int $state): string => User::genderLabel($state)),
                                    TextEntry::make('phone')->label('手机号')->copyable()->placeholder('—'),
                                    TextEntry::make('created_at')->label('注册时间')->dateTime('Y-m-d H:i:s'),
                                    TextEntry::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i:s'),
                                ])->columns(2),

                            Section::make('微信身份')
                                ->schema([
                                    TextEntry::make('openid')->label('OpenID')->copyable()->placeholder('—'),
                                    TextEntry::make('unionid')->label('UnionID')->copyable()->placeholder('—'),
                                ])->collapsible(),
                        ]),

                    Section::make('安全与状态')
                        ->schema([
                            TextEntry::make('status')->label('账户状态')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => User::statusLabel($state))
                                ->color(fn (string $state): string => $state === User::STATUS_BANNED ? 'danger' : 'success'),
                            TextEntry::make('banned_at')->label('封禁时间')
                                ->dateTime('Y-m-d H:i:s')->placeholder('—'),
                            TextEntry::make('ban_reason')->label('封禁原因')->placeholder('—'),
                            TextEntry::make('name')->label('后台姓名')->placeholder('—'),
                            TextEntry::make('email')->label('后台邮箱')->placeholder('—'),
                            TextEntry::make('roles.name')->label('后台角色')
                                ->badge()->listWithLineBreaks()->bulleted()->placeholder('—'),
                            TextEntry::make('tokens_count')->label('登录态 Token 数')
                                ->state(fn (User $record) => $record->tokens()->count())
                                ->numeric(),
                        ]),

                Section::make('扩展信息 Meta')
                    ->schema([
                        KeyValueEntry::make('meta')
                            ->keyLabel('键')
                            ->valueLabel('值')
                            ->placeholder('（空）'),
                    ])
                    ->collapsible()
                    ->compact(),

                Tabs::make('关联数据')
                    ->tabs([
                        self::buildFeedbacksTab(),
                        self::buildNotificationsTab(),
                        self::buildTokensTab(),
                        self::buildAuditsTab(),
                    ]),
        ];
    }

    public static function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components(static::infolistComponents());
    }

    public static function buildFeedbacksTab(): Tab
    {
        return Tab::make('反馈记录')
            ->badge(fn (User $record): ?string => $record->feedbacks()->count() ?: null)
            ->schema([
                Section::make()
                    ->schema([
                        RepeatableEntry::make('feedbacks')
                            ->label('用户反馈')
                            ->hiddenLabel()
                            ->state(fn (User $record) => $record->feedbacks)
                            ->schema([
                                TextEntry::make('id')->label('ID'),
                                TextEntry::make('type')->label('类型')->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'bug' => '缺陷',
                                        'complaint' => '投诉',
                                        'suggestion' => '建议',
                                        default => '其他',
                                    }),
                                TextEntry::make('content')->label('内容')->limit(50)->columnSpanFull(),
                                TextEntry::make('status')->label('状态')->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'pending' => '待处理',
                                        'processing' => '处理中',
                                        'resolved' => '已解决',
                                        'rejected' => '已驳回',
                                        default => $state,
                                    }),
                                TextEntry::make('handle_note')->label('处理备注')->placeholder('—')->columnSpanFull(),
                                TextEntry::make('handler.name')->label('处理人')->placeholder('—'),
                                TextEntry::make('created_at')->label('提交时间')->dateTime('Y-m-d H:i'),
                                TextEntry::make('handled_at')->label('处理时间')->dateTime('Y-m-d H:i')->placeholder('—'),
                            ])->columns(3)
                            ->helperText(fn (User $record) => $record->feedbacks()->count() === 0 ? '暂无反馈记录。' : ''),
                    ])
                    ->footerActions([
                        Action::make('view_all_feedbacks')
                            ->label('前往反馈管理页（已筛选该用户）')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->link()
                            ->url(fn (User $record): string => route('filament.admin.resources.feedback.index')
                                .'?tableFilters[user][value]='.$record->id)
                            ->openUrlInNewTab(),
                    ]),
            ]);
    }

    public static function buildNotificationsTab(): Tab
    {
        return Tab::make('站内通知')
            ->badge(fn (User $record): ?string => $record->notifications()->count() ?: null)
            ->schema([
                Section::make()
                    ->schema([
                        RepeatableEntry::make('notifications')
                            ->label('收到的通知')
                            ->hiddenLabel()
                            ->state(fn (User $record) => $record->notifications()->withPivot('read', 'read_at', 'created_at')->get())
                            ->schema([
                                TextEntry::make('id')->label('ID'),
                                TextEntry::make('title')->label('标题'),
                                TextEntry::make('type')->label('类型')->badge()
                                    ->formatStateUsing(fn (string $state) => match ($state) { 'activity' => '活动', 'version' => '版本更新', default => '系统通知', }),
                                TextEntry::make('pivot.read')->label('已读状态')->badge()
                                    ->formatStateUsing(fn ($state) => $state ? '已读' : '未读')
                                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                                TextEntry::make('pivot.read_at')->label('已读时间')->dateTime('Y-m-d H:i')->placeholder('—'),
                                TextEntry::make('pivot.created_at')->label('收到时间')->dateTime('Y-m-d H:i'),
                            ])->columns(2)
                            ->helperText(fn (User $record) => $record->notifications()->count() === 0 ? '暂无通知。' : ''),
                    ])
                    ->footerActions([
                        Action::make('view_all_notifications')
                            ->label('前往通知管理页（已筛选该用户）')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->link()
                            ->url(fn (User $record): string => route('filament.admin.resources.notifications.index')
                                .'?tableFilters[user][value]='.$record->id)
                            ->openUrlInNewTab(),
                    ]),
            ]);
    }

    public static function buildTokensTab(): Tab
    {
        return Tab::make('登录态 Token')
            ->badge(fn (User $record): ?string => $record->tokens()->count() ?: null)
            ->schema([
                Section::make()
                    ->schema([
                        RepeatableEntry::make('tokens')
                            ->label('登录会话')
                            ->hiddenLabel()
                            ->state(fn (User $record) => $record->tokens)
                            ->schema([
                                TextEntry::make('name')->label('名称'),
                                TextEntry::make('abilities')->label('能力')
                                    ->formatStateUsing(fn ($state) => is_array($state) && count($state) > 0 ? implode(', ', $state) : '默认（*）'),
                                TextEntry::make('last_used_at')->label('最近使用')->dateTime('Y-m-d H:i')->placeholder('—'),
                                TextEntry::make('expires_at')->label('过期时间')->dateTime('Y-m-d H:i')->placeholder('—'),
                                TextEntry::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i'),
                            ])->columns(2)
                            ->helperText(fn (User $record) => $record->tokens()->count() === 0 ? '当前没有登录态。' : 'Token 字段不可见，只支持一键撤销；封禁用户会自动执行此操作。'),
                    ])
                    ->footerActions([
                        Action::make('view_all_tokens')
                            ->label('前往 Token 管理页（已筛选该用户）')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->link()
                            ->url(fn (User $record): string => route('filament.admin.resources.tokens.index')
                                .'?tableFilters[user][value]='.$record->id)
                            ->openUrlInNewTab(),
                    ]),
            ]);
    }

    public static function buildAuditsTab(): Tab
    {
        return Tab::make('操作审计')
            ->badge(fn (User $record): ?string => AuditLog::where('user_id', $record->id)->count() ?: null)
            ->schema([
                Section::make()
                    ->schema([
                        RepeatableEntry::make('recent_audits')
                            ->label('最近 20 条操作记录')
                            ->hiddenLabel()
                            ->state(fn (User $record) => AuditLog::where('user_id', $record->id)->orderByDesc('created_at')->limit(20)->get())
                            ->schema([
                                TextEntry::make('type')->label('动作')->badge()
                                    ->formatStateUsing(fn (string $state) => match ($state) { 'create' => '新增', 'update' => '修改', 'delete' => '删除', 'pivot' => '关联变更', default => $state, })
                                    ->color(fn (string $state) => match ($state) { 'create' => 'success', 'update' => 'info', 'delete' => 'danger', 'pivot' => 'warning', default => 'gray', }),
                                TextEntry::make('module')->label('模块'),
                                TextEntry::make('target_id')->label('目标 ID')->placeholder('—'),
                                TextEntry::make('summary')->label('摘要')->limit(60)->placeholder('—')->columnSpanFull(),
                                TextEntry::make('ip')->label('IP')->placeholder('—'),
                                TextEntry::make('created_at')->label('时间')->dateTime('Y-m-d H:i:s'),
                            ])->columns(3)
                            ->helperText(function (User $record): string {
                                $count = AuditLog::where('user_id', $record->id)->count();
                                if ($count === 0) {
                                    return '暂无操作记录。';
                                }

                                return "共 {$count} 条记录，此处展示最近 20 条。查看完整审计请到「系统管理 → 审计日志」筛选操作人。";
                            }),
                    ])
                    ->footerActions([
                        Action::make('view_all_audits')
                            ->label('前往审计日志管理页（已筛选该用户）')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->link()
                            ->url(fn (User $record): string => route('filament.admin.resources.audit-logs.index')
                                .'?tableFilters[user][value]='.$record->id)
                            ->openUrlInNewTab(),
                    ]),
            ]);
    }

    /**
     * 撤销该用户全部登录态（一处定义，列表行内 / 详情弹窗底部共同复用）。
     */
    public static function revokeAllTokensAction(): Action
    {
        return Action::make('revokeAllTokens')
            ->label('撤销全部 Token')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (User $record): void {
                $record->tokens()->delete();
                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('已撤销该用户全部登录态')
                    ->send();
            });
    }

    /**
     * 封禁用户（带原因表单）。详情弹窗底部复用时隐藏式样由调用处可见性兜底。
     */
    public static function banAction(): Action
    {
        return Action::make('ban')
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
            });
    }

    /**
     * 解封用户。
     */
    public static function unbanAction(): Action
    {
        return Action::make('unban')
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
            });
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
                    ->formatStateUsing(fn (int $state): string => User::genderLabel($state))
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
                    ->formatStateUsing(fn (string $state): string => User::statusLabel($state))
                    ->color(fn (string $state): string => $state === User::STATUS_BANNED ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('banned_at')
                    ->label('封禁时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordClasses(fn (User $record): ?string => $record->status === User::STATUS_BANNED ? 'fi-ta-row-banned' : null)
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        User::STATUS_NORMAL => User::statusLabel(User::STATUS_NORMAL),
                        User::STATUS_BANNED => User::statusLabel(User::STATUS_BANNED),
                    ]),
                SelectFilter::make('gender')
                    ->label('性别')
                    ->options([
                        0 => User::genderLabel(0),
                        1 => User::genderLabel(1),
                        2 => User::genderLabel(2),
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
                ViewAction::make()
                    ->schema(static::infolistComponents())
                    ->modalWidth(\Filament\Support\Enums\Width::ThreeExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    // 详情弹窗底部操作条：看用户详情时即可直接处理，无需回到列表行
                    ->modalFooterActions([
                        static::revokeAllTokensAction(),
                        static::banAction(),
                        static::unbanAction(),
                    ]),
                EditAction::make(),
                static::revokeAllTokensAction(),
                static::banAction(),
                static::unbanAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                self::buildExportAllHeaderAction(
                    baseQuery: User::query(),
                    columnMap: [
                        'id' => 'ID',
                        'nickname' => '昵称',
                        'phone' => '手机号',
                        'gender_txt' => '性别',
                        'status_txt' => '状态',
                        'openid' => 'OpenID',
                        'unionid' => 'UnionID',
                        'created_at_txt' => '注册时间',
                        'banned_at_txt' => '封禁时间',
                        'ban_reason' => '封禁原因',
                        'roles_txt' => '后台角色',
                    ],
                    label: '导出全部用户',
                    fileNamePrefix: 'users',
                    rowCallback: static fn (User $u): array => [
                        $u->id,
                        (string) $u->nickname,
                        (string) $u->phone,
                        User::genderLabel($u->gender),
                        User::statusLabel($u->status),
                        (string) $u->openid,
                        (string) $u->unionid,
                        $u->created_at?->format('Y-m-d H:i:s') ?? '',
                        $u->banned_at?->format('Y-m-d H:i:s') ?? '',
                        (string) $u->ban_reason,
                        $u->roles->pluck('name')->implode(' / '),
                    ],
                ),
                BulkActionGroup::make([
                    self::buildExportSelectedBulkAction(
                        columnMap: [
                            'id' => 'ID',
                            'nickname' => '昵称',
                            'phone' => '手机号',
                            'gender_txt' => '性别',
                            'status_txt' => '状态',
                            'openid' => 'OpenID',
                            'created_at_txt' => '注册时间',
                            'roles_txt' => '角色',
                        ],
                        label: '导出所选 CSV',
                        fileNamePrefix: 'users',
                        rowCallback: static fn (User $u): array => [
                            $u->id,
                            (string) $u->nickname,
                            (string) $u->phone,
                            User::genderLabel($u->gender),
                            User::statusLabel($u->status),
                            (string) $u->openid,
                            $u->created_at?->format('Y-m-d H:i:s') ?? '',
                            $u->roles->pluck('name')->implode(' / '),
                        ],
                    ),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->enhanceListExperience()
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
        ];
    }
}
