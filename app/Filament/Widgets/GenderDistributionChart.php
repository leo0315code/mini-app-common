<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GenderDistributionChart extends ChartWidget
{
    public ?array $pageFilters = null;

    protected ?string $heading = '用户性别分布';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $query = User::query()->whereNotNull('openid');

        $dates = Dashboard::rangeDates($this->pageFilters['range'] ?? null);
        if ($dates['start'] && $dates['end']) {
            $query->whereBetween('created_at', [$dates['start'], $dates['end']]);
        }

        $data = $query
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->groupBy('gender')
            ->pluck('count', 'gender');

        return [
            'datasets' => [
                [
                    'data' => [
                        $data->get(0, 0),
                        $data->get(1, 0),
                        $data->get(2, 0),
                    ],
                    'backgroundColor' => ['#9ca3af', '#3b82f6', '#ec4899'],
                    'borderColor' => ['#6b7280', '#2563eb', '#db2777'],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => ['未知', '男', '女'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
