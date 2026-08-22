<?php

namespace App\Filament\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\GenderDistributionChart;
use App\Filament\Widgets\OpexStatsWidget;
use App\Filament\Widgets\PendingFeedbackTable;
use App\Filament\Widgets\RecentUsersTable;
use App\Filament\Widgets\UserRegistrationChart;
use App\Filament\Widgets\UserStatsWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationLabel = '工作台';

    protected static ?int $navigationSort = -2;

    /** 时间范围选项：key => 天数（null 表示全部） */
    public static array $rangeOptions = [
        '7' => '近 7 天',
        '30' => '近 30 天',
        '90' => '近 90 天',
        'all' => '全部',
    ];

    public function getTitle(): string
    {
        return '工作台';
    }

    public function getSubheading(): string
    {
        return '小程序用户数据总览';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('range')
                    ->label('时间范围')
                    ->options(self::$rangeOptions)
                    ->default('30')
                    ->native(false)
                    ->live(),
            ]);
    }

    public function getHeaderActions(): array
    {
        return [
            FilterAction::make(),
            Action::make('view_users')
                ->label('查看全部用户')
                ->icon('heroicon-o-users')
                ->url(UserResource::getUrl('index')),
        ];
    }

    /**
     * 根据筛选的 range 计算起止时间。
     *
     * @return array{start: \Illuminate\Support\Carbon|null, end: \Illuminate\Support\Carbon|null}
     */
    public static function rangeDates(?string $range): array
    {
        if (blank($range) || $range === 'all') {
            return ['start' => null, 'end' => null];
        }

        $days = (int) $range;

        return [
            'start' => now()->subDays($days)->startOfDay(),
            'end' => now()->endOfDay(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('欢迎回来 👋')
                    ->description('这里汇总了小程序用户与运营的核心指标及最新动态。')
                    ->aside()
                    ->schema([
                        UnorderedList::make([
                            Text::make('上方统计卡片：总用户数、手机绑定率，以及待处理反馈、通知已读率、内容/媒体、今日 API 调用、封禁用户等运营指标。'),
                            Text::make('「用户注册趋势」与「性别分布」图表帮助了解增长与结构。'),
                            Text::make('「最近注册用户」与「待处理反馈」表列出最新动态，可点进对应资源操作。'),
                        ]),
                    ])
                    ->collapsible(),

                ...$this->getWidgetsSchemaComponents([
                    UserStatsWidget::class,
                    OpexStatsWidget::class,
                ]),

                Grid::make(2)
                    ->schema(
                        $this->getWidgetsSchemaComponents([
                            UserRegistrationChart::class,
                            GenderDistributionChart::class,
                        ])
                    ),

                ...$this->getWidgetsSchemaComponents([
                    RecentUsersTable::class,
                    PendingFeedbackTable::class,
                ]),
            ]);
    }
}
