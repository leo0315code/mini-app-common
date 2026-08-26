<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Support\HasWidgetPermission;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GenderDistributionChart extends ChartWidget
{
    use HasWidgetPermission;

    public ?array $pageFilters = null;

    protected ?string $heading = '用户性别分布';

    protected static ?int $sort = 3;

    protected static function getWidgetPermissions(): array
    {
        return ['dashboard.view', 'user.view'];
    }

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
                    'backgroundColor' => ['#0D9488', '#0EA5E9', '#6366F1'],
                    'borderColor' => ['#0c7d73', '#0284c7', '#4f46e5'],
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
