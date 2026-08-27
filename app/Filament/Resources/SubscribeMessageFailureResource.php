<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscribeMessageFailureResource\Pages;
use App\Models\SubscribeMessageFailure;
use App\Services\WechatService;
use App\Support\ExportsCsv;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SubscribeMessageFailureResource extends Resource
{
    use ExportsCsv;

    /** 业务级不可重试错误码（与 RetryFailedSubscribeMessages / Job 保持一致） */
    protected const NO_RETRY_CODES = [43101, 40037, 41030, 40003, -1, -2];

    /** 自动重试上限（与 subscribe:retry-failed 默认 max-attempts 一致） */
    protected const MAX_AUTO_RETRIES = 3;

    protected static ?string $model = SubscribeMessageFailure::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = '推送失败记录';

    protected static ?string $modelLabel = '推送失败记录';

    protected static ?string $pluralModelLabel = '推送失败记录';

    public static function getNavigationBadge(): ?string
    {
        $count = SubscribeMessageFailure::whereNull('resolved_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('基础信息')
                ->schema([
                    Forms\Components\TextInput::make('id')->label('ID')->disabled(),
                    Forms\Components\TextInput::make('scene')
                        ->label('场景')
                        ->disabled()
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'feedback_handled' => '反馈处理',
                            'announcement_published' => '公告发布',
                            'notification_published' => '站内通知',
                            'direct' => '直接推送',
                            default => (string) $state,
                        }),
                    Forms\Components\TextInput::make('subject_type')
                        ->label('关联类型')
                        ->disabled()
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            \App\Models\Feedback::class => '用户反馈',
                            \App\Models\Announcement::class => '公告',
                            \App\Models\Notification::class => '站内通知',
                            null => '—',
                            default => class_basename($state),
                        }),
                    Forms\Components\TextInput::make('subject_id')->label('关联ID')->disabled()->formatStateUsing(fn ($state) => $state ?? '—'),
                    Forms\Components\TextInput::make('openid')
                        ->label('OpenID')
                        ->disabled()
                        ->copyable()
                        ->formatStateUsing(fn (?string $state): string => $state && mb_strlen($state) > 12
                            ? mb_substr($state, 0, 6) . '***' . mb_substr($state, -4)
                            : (string) $state),
                    Forms\Components\TextInput::make('template_id')
                        ->label('模板ID')
                        ->disabled()
                        ->copyable(),
                ])->columns(3),
            Section::make('推送结果')
                ->schema([
                    Forms\Components\TextInput::make('attempts')
                        ->label('尝试次数')
                        ->disabled()
                        ->suffix('次'),
                    Forms\Components\TextInput::make('last_errcode')
                        ->label('错误码')
                        ->disabled()
                        ->helperText(fn ($record) => self::getErrcodeMeaning($record?->last_errcode)),
                    Forms\Components\TextInput::make('last_attempted_at')
                        ->label('最后尝试时间')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => $state && is_object($state) ? $state->format('Y-m-d H:i:s') : ((string) $state ?: '—')),
                    Forms\Components\Textarea::make('last_errmsg')
                        ->label('错误信息')
                        ->disabled()
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(3),
            Section::make('处理状态')
                ->schema([
                    Forms\Components\TextInput::make('resolved_status')
                        ->label('状态')
                        ->disabled()
                        ->formatStateUsing(fn ($state, $record): string => $record?->resolved_at ? '已解决' : '待处理'),
                    Forms\Components\TextInput::make('resolved_at')
                        ->label('解决时间')
                        ->disabled()
                        ->formatStateUsing(fn ($state) => $state && is_object($state) ? $state->format('Y-m-d H:i:s') : ((string) $state ?: '—')),
                    Forms\Components\Textarea::make('resolved_note')
                        ->label('解决备注')
                        ->disabled()
                        ->rows(2)
                        ->columnSpanFull()
                        ->formatStateUsing(fn ($state): string => $state ?? '—'),
                ])->columns(2),
            Section::make('推送载荷')
                ->schema([
                    Forms\Components\KeyValue::make('payload')
                        ->label('Payload')
                        ->disabled()
                        ->editableKeys(false)
                        ->addable(false)
                        ->deletable(false)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('page')
                        ->label('跳转页面')
                        ->disabled()
                        ->formatStateUsing(fn ($state): string => $state ?? '—'),
                    Forms\Components\TextInput::make('job_uuid')
                        ->label('队列Job UUID')
                        ->disabled()
                        ->copyable()
                        ->formatStateUsing(fn ($state): string => $state ?? '—'),
                ])->columns(2),
            Section::make('时间')
                ->schema([
                    Forms\Components\TextInput::make('created_at')->label('创建时间')->disabled()->formatStateUsing(fn ($state) => $state && is_object($state) ? $state->format('Y-m-d H:i:s') : ((string) $state ?: '—')),
                    Forms\Components\TextInput::make('updated_at')->label('更新时间')->disabled()->formatStateUsing(fn ($state) => $state && is_object($state) ? $state->format('Y-m-d H:i:s') : ((string) $state ?: '—')),
                ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('基础信息')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('scene')->label('场景')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'feedback_handled' => '反馈处理',
                        'announcement_published' => '公告发布',
                        'notification_published' => '站内通知',
                        'direct' => '直接推送',
                        default => (string) $state,
                    }),
                TextEntry::make('subject_type')->label('关联类型')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        \App\Models\Feedback::class => '用户反馈',
                        \App\Models\Announcement::class => '公告',
                        \App\Models\Notification::class => '站内通知',
                        null => '—',
                        default => class_basename($state),
                    }),
                TextEntry::make('subject_id')->label('关联ID')->placeholder('—'),
                TextEntry::make('openid')->label('OpenID')->copyable()
                    ->formatStateUsing(fn (?string $state): string => $state && mb_strlen($state) > 12 ? mb_substr($state, 0, 6) . '***' . mb_substr($state, -4) : (string) $state),
                TextEntry::make('template_id')->label('模板ID')->copyable()->placeholder('—'),
            ])->columns(2),
            Section::make('推送结果')->schema([
                TextEntry::make('attempts')->label('尝试次数')->suffix('次'),
                TextEntry::make('last_errcode')->label('错误码'),
                TextEntry::make('last_attempted_at')->label('最后尝试时间')
                    ->formatStateUsing(fn ($state) => $state && is_object($state) ? $state->format('Y-m-d H:i:s') : ((string) $state ?: '—')),
                TextEntry::make('last_errmsg')->label('错误信息')->placeholder('—')->columnSpanFull(),
            ])->columns(2),
            Section::make('处理状态')->schema([
                TextEntry::make('resolved_at')->label('状态')
                    ->formatStateUsing(fn ($record): string => $record->resolved_at ? '已解决' : '待处理')
                    ->badge()->color(fn ($record): string => $record->resolved_at ? 'success' : 'warning'),
                TextEntry::make('resolved_at')->label('解决时间')
                    ->formatStateUsing(fn ($state) => $state && is_object($state) ? $state->format('Y-m-d H:i:s') : ((string) $state ?: '—')),
                TextEntry::make('resolved_note')->label('解决备注')->placeholder('—')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('scene')
                    ->label('场景')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'feedback_handled' => '反馈处理',
                        'announcement_published' => '公告发布',
                        'notification_published' => '站内通知',
                        'direct' => '直接推送',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'feedback_handled' => 'info',
                        'announcement_published' => 'success',
                        'notification_published' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('openid')
                    ->label('OpenID')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => mb_strlen($state) > 12
                        ? mb_substr($state, 0, 6) . '***' . mb_substr($state, -4)
                        : $state)
                    ->tooltip(fn ($record) => $record->openid)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('template_id')
                    ->label('模板ID')
                    ->searchable()
                    ->limit(16)
                    ->tooltip(fn ($record) => $record->template_id)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('attempts')
                    ->label('次数')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => (string) $state)
                    ->color(fn (int $state): string => match (true) {
                        $state >= 5 => 'danger',
                        $state >= 3 => 'warning',
                        default => 'info',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_errcode')
                    ->label('错误码')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => (string) ($state ?? '—'))
                    ->color(fn ($state): string => match (true) {
                        $state === null => 'gray',
                        $state === 0 => 'success',
                        in_array($state, [43101, 40037, 41030, 40003], true) => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(fn ($record) => self::getErrcodeMeaning($record->last_errcode))
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_errmsg')
                    ->label('错误信息')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->last_errmsg),
                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(function ($state, $record): string {
                        if ($record->resolved_at) {
                            return '已解决';
                        }

                        // 不可重试的业务级错误码（用户拒收/模板无效等）→ 需人工处理
                        if (in_array((int) ($record->last_errcode ?? 0), self::NO_RETRY_CODES, true)) {
                            return '需人工处理';
                        }

                        return $record->attempts >= self::MAX_AUTO_RETRIES
                            ? '重试耗尽'
                            : '待自动重试';
                    })
                    ->color(fn ($state, $record): string => match (true) {
                        $record->resolved_at => 'success',
                        in_array((int) ($record->last_errcode ?? 0), self::NO_RETRY_CODES, true) => 'warning',
                        $record->attempts >= self::MAX_AUTO_RETRIES => 'danger',
                        default => 'info',
                    })
                    ->tooltip(function ($record): string {
                        if ($record->resolved_at) {
                            return '已解决'.($record->resolved_note ? '：'.$record->resolved_note : '');
                        }

                        if (in_array((int) ($record->last_errcode ?? 0), self::NO_RETRY_CODES, true)) {
                            return '该错误为业务级不可重试（如用户取消订阅），需人工判断是否可修复后手动重发';
                        }

                        if ($record->attempts >= self::MAX_AUTO_RETRIES) {
                            return '自动重试已达上限，需人工处理（可修复后手动重发）';
                        }

                        return sprintf(
                            '将每 5 分钟自动重试，已重试 %d 次，剩余 %d 次机会',
                            $record->attempts,
                            max(0, self::MAX_AUTO_RETRIES - $record->attempts),
                        );
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_attempted_at')
                    ->label('最后尝试')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('scene')
                    ->label('场景')
                    ->options([
                        'feedback_handled' => '反馈处理',
                        'announcement_published' => '公告发布',
                        'notification_published' => '站内通知',
                        'direct' => '直接推送',
                    ]),
                SelectFilter::make('last_errcode')
                    ->label('错误码')
                    ->options([
                        43101 => '43101 用户未订阅',
                        40037 => '40037 模板ID无效',
                        41030 => '41030 页面路径错误',
                        40003 => '40003 OpenID无效',
                        40001 => '40001 AccessToken失效',
                        42001 => '42001 AccessToken过期',
                        -1 => '-1 系统繁忙',
                        -999 => '-999 队列最终失败',
                    ]),
                TernaryFilter::make('resolved')
                    ->label('状态')
                    ->placeholder('全部')
                    ->trueLabel('已解决')
                    ->falseLabel('待处理')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNull('resolved_at'),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\Filter::make('last_attempted_at')
                    ->label('最后尝试时间')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('attempted_from')->label('从'),
                        \Filament\Forms\Components\DatePicker::make('attempted_until')->label('到'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['attempted_from'],
                                fn (Builder $q, $date) => $q->whereDate('last_attempted_at', '>=', $date),
                            )
                            ->when(
                                $data['attempted_until'],
                                fn (Builder $q, $date) => $q->whereDate('last_attempted_at', '<=', $date),
                            );
                    }),
            ])
            ->filtersFormColumns(4)
            ->recordActions([
                ViewAction::make(),
                Action::make('resend')
                    ->label('重发')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('确认重发')
                    ->modalDescription('将重新调用微信接口推送此条订阅消息，成功后会标记为已解决；失败则更新错误信息。')
                    ->action(function (SubscribeMessageFailure $record): void {
                        self::resendSingle($record);
                    }),
                Action::make('markResolved')
                    ->label('标记已解决')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('标记为已解决')
                    ->modalDescription('确认这条失败记录不再需要重发，将手动标记为已解决。')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('resolved_note')
                            ->label('解决备注')
                            ->rows(3)
                            ->placeholder('例如：用户已取消订阅，无需再发'),
                    ])
                    ->action(function (SubscribeMessageFailure $record, array $data): void {
                        $record->update([
                            'resolved_at' => now(),
                            'resolved_note' => $data['resolved_note'] ?? '手动标记为已解决',
                        ]);

                        Notification::make()
                            ->success()
                            ->title('已标记为已解决')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                Action::make('onlyUnresolved')
                    ->label('仅看待处理')
                    ->icon('heroicon-o-exclamation-circle')
                    ->color('danger')
                    ->action(function () use ($table): void {
                        $this->tableFilters['resolved'] = ['value' => false];
                        $this->resetTable();
                    }),
                self::buildExportAllHeaderAction(
                    baseQuery: SubscribeMessageFailure::query(),
                    columnMap: [
                        'id' => 'ID',
                        'scene_txt' => '场景',
                        'openid' => 'OpenID',
                        'subject' => '关联对象',
                        'template_id' => '模板ID',
                        'attempts' => '尝试次数',
                        'last_errcode' => '错误码',
                        'errmsg' => '错误信息',
                        'status_txt' => '状态',
                        'last_attempted_at_txt' => '最后尝试时间',
                        'created_at_txt' => '创建时间',
                    ],
                    label: '导出全部失败记录',
                    fileNamePrefix: 'subscribe-failures',
                    rowCallback: static fn (SubscribeMessageFailure $f): array => [
                        $f->id,
                        match ((string) $f->scene) {
                            'feedback_handled' => '反馈处理',
                            'announcement_published' => '公告发布',
                            'notification_published' => '站内通知',
                            default => (string) $f->scene,
                        },
                        (string) $f->openid,
                        $f->subject_type && $f->subject_id
                            ? class_basename((string) $f->subject_type).'#'.$f->subject_id
                            : '—',
                        (string) $f->template_id,
                        (int) $f->attempts,
                        (string) ($f->last_errcode ?? ''),
                        (string) $f->last_errmsg,
                        $f->resolved_at ? '已解决' : '待处理',
                        $f->last_attempted_at?->format('Y-m-d H:i:s') ?? '',
                        $f->created_at?->format('Y-m-d H:i:s') ?? '',
                    ],
                ),
                BulkActionGroup::make([
                    self::buildExportSelectedBulkAction(
                        columnMap: [
                            'id' => 'ID',
                            'scene_txt' => '场景',
                            'openid' => 'OpenID',
                            'subject' => '关联对象',
                            'template_id' => '模板ID',
                            'attempts' => '尝试次数',
                            'last_errcode' => '错误码',
                            'errmsg' => '错误信息',
                            'status_txt' => '状态',
                            'last_attempted_at_txt' => '最后尝试时间',
                            'created_at_txt' => '创建时间',
                        ],
                        label: '导出所选',
                        fileNamePrefix: 'subscribe-failures',
                        rowCallback: static fn (SubscribeMessageFailure $f): array => [
                            $f->id,
                            match ((string) $f->scene) {
                                'feedback_handled' => '反馈处理',
                                'announcement_published' => '公告发布',
                                'notification_published' => '站内通知',
                                default => (string) $f->scene,
                            },
                            (string) $f->openid,
                            $f->subject_type && $f->subject_id
                                ? class_basename((string) $f->subject_type).'#'.$f->subject_id
                                : '—',
                            (string) $f->template_id,
                            (int) $f->attempts,
                            (string) ($f->last_errcode ?? ''),
                            (string) $f->last_errmsg,
                            $f->resolved_at ? '已解决' : '待处理',
                            $f->last_attempted_at?->format('Y-m-d H:i:s') ?? '',
                            $f->created_at?->format('Y-m-d H:i:s') ?? '',
                        ],
                    ),
                    BulkAction::make('bulkResend')
                        ->label('批量重发')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading('批量重发确认')
                        ->modalDescription(fn (Collection $records) => sprintf(
                            '将对 %d 条失败记录执行重新推送，成功的会标记为已解决，失败的会更新错误信息。是否继续？',
                            count($records),
                        ))
                        ->action(function (Collection $records): void {
                            $success = 0;
                            $failed = 0;

                            $records->each(function (SubscribeMessageFailure $record) use (&$success, &$failed) {
                                $result = self::resendInternal($record);
                                if ($result) {
                                    $success++;
                                } else {
                                    $failed++;
                                }
                            });

                            Notification::make()
                                ->success()
                                ->title("批量重发完成：成功 {$success} 条，失败 {$failed} 条")
                                ->send();
                        }),
                    BulkAction::make('bulkMarkResolved')
                        ->label('批量标记已解决')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading('批量标记为已解决')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('resolved_note')
                                ->label('解决备注')
                                ->rows(2)
                                ->default('批量手动标记为已解决'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $note = $data['resolved_note'] ?? '批量手动标记为已解决';
                            $records->each(fn (SubscribeMessageFailure $record) => $record->update([
                                'resolved_at' => now(),
                                'resolved_note' => $note,
                            ]));

                            Notification::make()
                                ->success()
                                ->title('已批量标记 '.count($records).' 条为已解决')
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn (SubscribeMessageFailure $record): ?string => $record->resolved_at === null ? 'fi-ta-row-danger' : null)
            ->enhanceListExperience()

            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscribeMessageFailures::route('/'),
        ];
    }

    protected static function resendSingle(SubscribeMessageFailure $record): void
    {
        $result = self::resendInternal($record);

        if ($result) {
            Notification::make()
                ->success()
                ->title('重发成功')
                ->body('已成功推送，记录已标记为已解决。')
                ->send();
        } else {
            Notification::make()
                ->danger()
                ->title('重发失败')
                ->body('请查看错误码和错误信息，稍后可再次尝试。')
                ->send();
        }
    }

    protected static function resendInternal(SubscribeMessageFailure $record): bool
    {
        try {
            /** @var WechatService $wechatService */
            $wechatService = app(WechatService::class);

            $payload = $record->payload ?? [];
            $data = $payload['data'] ?? [];
            $page = $record->page ?? ($payload['page'] ?? null);
            $options = $payload['options'] ?? [];

            $result = $wechatService->sendSubscribeMessage(
                openid: $record->openid,
                templateId: $record->template_id,
                data: $data,
                page: $page,
                options: $options,
            );

            if ($result['success']) {
                $record->update([
                    'resolved_at' => now(),
                    'resolved_note' => '手动重发成功',
                    'attempts' => $record->attempts + 1,
                    'last_errcode' => 0,
                    'last_errmsg' => 'ok',
                    'last_attempted_at' => now(),
                ]);

                return true;
            }

            $record->update([
                'attempts' => $record->attempts + 1,
                'last_errcode' => $result['errcode'],
                'last_errmsg' => $result['errmsg'],
                'last_attempted_at' => now(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $record->update([
                'attempts' => $record->attempts + 1,
                'last_errcode' => -999,
                'last_errmsg' => '重发异常：' . $e->getMessage(),
                'last_attempted_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * 测试 / 外部调用：resendInternal 的公开代理
     */
    public static function resendInternalProxy(SubscribeMessageFailure $record): bool
    {
        return self::resendInternal($record);
    }

    protected static function getErrcodeMeaning($code): ?string
    {
        if ($code === null) {
            return null;
        }

        return match ((int) $code) {
            0 => '发送成功',
            43101 => '用户拒收该消息（未订阅或已取消订阅）',
            40037 => '模板 ID 无效或不存在',
            41030 => '小程序页面路径不存在或格式错误',
            40003 => 'OpenID 无效或不属于该小程序',
            40001 => 'access_token 无效，一般会自动重试获取',
            42001 => 'access_token 已过期，一般会自动刷新重试',
            40014 => 'access_token 不合法',
            40002 => '缺少 openid 参数',
            40005 => '模板内容格式错误',
            47001 => 'data 格式错误',
            -1 => '微信系统繁忙，稍后可重试',
            -2 => '网络错误或参数异常',
            -999 => '队列重试耗尽或重发时发生异常',
            default => '其他错误，请参考微信官方文档',
        };
    }
}
