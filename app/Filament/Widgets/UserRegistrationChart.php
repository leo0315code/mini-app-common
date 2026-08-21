<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class UserRegistrationChart extends ChartWidget
{
    public ?array $pageFilters = null;

    protected ?string $heading = '用户注册趋势';

    protected static ?int $sort = 2;

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
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
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
