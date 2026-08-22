<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\KeyValue;
use Filament\Schemas\Components\RepeatableEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Split;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var User $record */
        $record = $this->record;

        return sprintf('用户详情 #%d  %s', $record->id, $record->nickname ?? '');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Split::make([
                    Grid::make(2)
                        ->schema([
                            Section::make('基本信息')
                                ->schema([
                                    TextEntry::make('id')->label('ID'),
                                    TextEntry::make('nickname')->label('昵称'),
                                    TextEntry::make('avatar')->label('头像')
                                        ->size(64)
                                        ->circular()
                                        ->defaultImageUrl('https://ui-avatars.com/api/?name=User&background=random')
                                        ->placeholder('—'),
                                    TextEntry::make('gender')->label('性别')
                                        ->formatStateUsing(fn (int $state): string => match ($state) {
                                            1 => '男',
                                            2 => '女',
                                            default => '未知',
                                        }),
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
                                ->formatStateUsing(fn (string $state): string => $state === User::STATUS_BANNED ? '已封禁' : '正常')
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
                        ])->grow(false),
                ])->from('md'),

                Section::make('扩展信息 Meta')
                    ->schema([
                        KeyValue::make('meta')
                            ->keyLabel('键')
                            ->valueLabel('值')
                            ->placeholder('（空）'),
                    ])
                    ->collapsible()
                    ->compact(),

                Tabs::make('关联数据')
                    ->tabs([
                        $this->buildFeedbacksTab(),
                        $this->buildNotificationsTab(),
                        $this->buildTokensTab(),
                        $this->buildAuditsTab(),
                    ])
                    ->persistTabInQueryString(),
            ]);
    }

    protected function buildFeedbacksTab(): Tabs\Tab
    {
        return Tabs\Tab::make('反馈记录')
            ->badge(fn (User $record): ?string => $record->feedbacks()->count() ?: null)
            ->schema([
                RepeatableEntry::make('feedbacks')
                    ->label('用户反馈')
                    ->hiddenLabel()
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
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addable(false)
                    ->deletable(false)
                    ->helperText(fn (User $record) => $record->feedbacks()->count() === 0 ? '暂无反馈记录。' : ''),
            ]);
    }

    protected function buildNotificationsTab(): Tabs\Tab
    {
        return Tabs\Tab::make('站内通知')
            ->badge(fn (User $record): ?string => $record->notifications()->count() ?: null)
            ->schema([
                RepeatableEntry::make('notifications')
                    ->label('收到的通知')
                    ->hiddenLabel()
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('title')->label('标题'),
                        TextEntry::make('type')->label('类型')->badge()
                            ->formatStateUsing(fn (string $s) => match ($s) { 'activity' => '活动', 'version' => '版本更新', default => '系统通知', }),
                        TextEntry::make('pivot.read')->label('已读状态')->badge()
                            ->formatStateUsing(fn ($s) => $s ? '已读' : '未读')
                            ->color(fn ($s) => $s ? 'success' : 'gray'),
                        TextEntry::make('pivot.read_at')->label('已读时间')->dateTime('Y-m-d H:i')->placeholder('—'),
                        TextEntry::make('pivot.created_at')->label('收到时间')->dateTime('Y-m-d H:i'),
                    ])->columns(2)
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addable(false)
                    ->deletable(false)
                    ->helperText(fn (User $record) => $record->notifications()->count() === 0 ? '暂无通知。' : ''),
            ]);
    }

    protected function buildTokensTab(): Tabs\Tab
    {
        return Tabs\Tab::make('登录态 Token')
            ->badge(fn (User $record): ?string => $record->tokens()->count() ?: null)
            ->footerActions([
                Actions\Action::make('revokeAll')
                    ->label('撤销全部 Token')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $this->record->tokens()->delete();
                        \Filament\Notifications\Notification::make()->success()->title('已撤销该用户全部登录态')->send();
                        $this->fillForm();
                    }),
            ])
            ->schema([
                RepeatableEntry::make('tokens')
                    ->label('登录会话')
                    ->hiddenLabel()
                    ->schema([
                        TextEntry::make('name')->label('名称'),
                        TextEntry::make('abilities')->label('能力')
                            ->formatStateUsing(fn ($s) => is_array($s) && count($s) > 0 ? implode(', ', $s) : '默认（*）'),
                        TextEntry::make('last_used_at')->label('最近使用')->dateTime('Y-m-d H:i')->placeholder('—'),
                        TextEntry::make('expires_at')->label('过期时间')->dateTime('Y-m-d H:i')->placeholder('—'),
                        TextEntry::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i'),
                    ])->columns(2)
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addable(false)
                    ->deletable(false)
                    ->helperText(fn (User $record) => $record->tokens()->count() === 0 ? '当前没有登录态。' : 'Token 字段不可见，只支持一键撤销；封禁用户会自动执行此操作。'),
            ]);
    }

    protected function buildAuditsTab(): Tabs\Tab
    {
        return Tabs\Tab::make('操作审计')
            ->badge(fn (User $record): ?string => AuditLog::where('actor_id', $record->id)->count() ?: null)
            ->schema([
                RepeatableEntry::make('recent_audits')
                    ->label('最近 20 条操作记录')
                    ->hiddenLabel()
                    ->relationship(fn ($q) => $q->orderByDesc('created_at')->limit(20))
                    ->schema([
                        TextEntry::make('type')->label('动作')->badge()
                            ->formatStateUsing(fn (string $s) => match ($s) { 'create' => '新增', 'update' => '修改', 'delete' => '删除', 'pivot' => '关联变更', default => $s, })
                            ->color(fn (string $s) => match ($s) { 'create' => 'success', 'update' => 'info', 'delete' => 'danger', 'pivot' => 'warning', default => 'gray', }),
                        TextEntry::make('module')->label('模块'),
                        TextEntry::make('target_id')->label('目标 ID')->placeholder('—'),
                        TextEntry::make('summary')->label('摘要')->limit(60)->placeholder('—')->columnSpanFull(),
                        TextEntry::make('ip')->label('IP')->placeholder('—'),
                        TextEntry::make('created_at')->label('时间')->dateTime('Y-m-d H:i:s'),
                    ])->columns(3)
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addable(false)
                    ->deletable(false)
                    ->helperText(function (User $record): string {
                        $count = AuditLog::where('actor_id', $record->id)->count();
                        if ($count === 0) {
                            return '暂无操作记录。';
                        }

                        return "共 {$count} 条记录，此处展示最近 20 条。查看完整审计请到「系统管理 → 审计日志」筛选操作人。";
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('ban')
                ->label('封禁')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (User $record): bool => $record->status !== User::STATUS_BANNED)
                ->form([
                    \Filament\Forms\Components\Textarea::make('ban_reason')->label('封禁原因')->rows(2),
                ])
                ->action(function (User $record, array $data): void {
                    $record->ban($data['ban_reason'] ?? null);
                    \Filament\Notifications\Notification::make()->success()->title('已封禁该用户')->send();
                    $this->fillForm();
                }),
            Actions\Action::make('unban')
                ->label('解封')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (User $record): bool => $record->status === User::STATUS_BANNED)
                ->action(function (User $record): void {
                    $record->unban();
                    \Filament\Notifications\Notification::make()->success()->title('已解封该用户')->send();
                    $this->fillForm();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
