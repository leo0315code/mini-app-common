<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UserStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers = User::count();
        $todayUsers = User::whereDate('created_at', today())->count();
        $weekUsers = User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $phoneUsers = User::whereNotNull('phone')->where('phone', '!=', '')->count();

        return [
            Stat::make('总用户数', $totalUsers)
                ->description('全部注册用户')
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make('今日新增', $todayUsers)
                ->description('今天注册的用户')
                ->icon('heroicon-o-user-plus')
                ->color('success'),

            Stat::make('本周新增', $weekUsers)
                ->description('本周注册的用户')
                ->icon('heroicon-o-calendar')
                ->color('warning'),

            Stat::make('已绑定手机', $phoneUsers)
                ->description($totalUsers > 0 ? round($phoneUsers / $totalUsers * 100, 1) . '%' : '0%')
                ->icon('heroicon-o-phone')
                ->color('danger'),
        ];
    }
}
