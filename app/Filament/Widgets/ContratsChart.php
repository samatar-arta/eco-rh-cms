<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\ChartWidget;

class ContratsChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Répartition des contrats';

    public static function canView(): bool
    {
        return RhStatsWidget::canView();
    }

    protected function getData(): array
    {
        $data = app(DashboardStatsService::class)->pour(auth()->user())['par_contrat'];

        return [
            'datasets' => [[
                'data' => array_values($data),
                'backgroundColor' => ['#d97706', '#0f766e', '#4338ca', '#be123c', '#4d7c0f', '#6b7280'],
            ]],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
