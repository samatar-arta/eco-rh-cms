<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\ChartWidget;

class EffectifsSocieteChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Effectifs par société';

    public static function canView(): bool
    {
        return RhStatsWidget::canView();
    }

    protected function getData(): array
    {
        $data = app(DashboardStatsService::class)->pour(auth()->user())['par_societe'];

        return [
            'datasets' => [['label' => 'Employés actifs', 'data' => array_values($data)]],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
