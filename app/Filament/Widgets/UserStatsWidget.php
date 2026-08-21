<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends BaseWidget
{
    public ?array $pageFilters = null;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers = User::count();
        $phoneUsers = User::whereNotNull('phone')->where('phone', '!=', '')->count();

        $range = $this->pageFilters['range'] ?? null;
        $dates = Dashboard::rangeDates($range);
        $query = User::query();
        if ($dates['start'] && $dates['end']) {
            $query->whereBetween('created_at', [$dates['start'], $dates['end']]);
        }
        $rangeUsers = (clone $query)->count();

        $rangeLabel = ($range && $range !== 'all')
            ? '近 ' . $range . ' 天新增'
            : '累计新增';

        return [
            Stat::make('总用户数', $totalUsers)
                ->description('全部注册用户')
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make($rangeLabel, $rangeUsers)
                ->description($rangeLabel . '的用户')
                ->icon('heroicon-o-user-plus')
                ->color('success'),

            Stat::make('手机绑定率', $totalUsers > 0 ? round($phoneUsers / $totalUsers * 100, 1) . '%' : '0%')
                ->description($phoneUsers . ' 人已绑定手机号')
                ->icon('heroicon-o-phone')
                ->color('danger'),

            Stat::make('当前筛选', $this->currentRangeLabel())
                ->description('可在右上角「筛选」中调整')
                ->icon('heroicon-o-funnel')
                ->color('warning'),
        ];
    }

    protected function currentRangeLabel(): string
    {
        $range = $this->pageFilters['range'] ?? 'all';

        return Dashboard::$rangeOptions[$range] ?? '全部';
    }
}
