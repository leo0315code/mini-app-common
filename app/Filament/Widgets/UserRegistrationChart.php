<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Support\HasWidgetPermission;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class UserRegistrationChart extends ChartWidget
{
    use HasWidgetPermission;

    public ?array $pageFilters = null;

    protected ?string $heading = '用户注册趋势';

    protected static ?int $sort = 2;

    protected static function getWidgetPermissions(): array
    {
        return ['dashboard.view', 'user.view'];
    }

    protected function getDayCount(): int
    {
        $range = $this->pageFilters['range'] ?? '30';

        if (! $range || $range === 'all') {
            return 30;
        }

        return (int) $range;
    }

    protected function getData(): array
    {
        $days = (int) $this->getDayCount();
        $start = now()->subDays($days - 1)->startOfDay();

        $dates = collect(range($days - 1, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $counts = User::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $start)
            ->groupBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => '新增用户',
                    'data' => $dates->map(fn ($day) => $counts->get($day, 0))->toArray(),
                    'borderColor' => '#0D9488',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.12)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $dates->map(fn ($day) => substr($day, 5))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
