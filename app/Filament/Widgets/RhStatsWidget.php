<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RhStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->is_active === true && auth()->user()->rolesEnum() !== [];
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $d = app(DashboardStatsService::class)->pour(auth()->user());
        $m = $d['modules'];
        $stats = [Stat::make('Employés actifs', $d['employes'])->color('primary')];

        if (isset($m['DOCUMENT'])) {
            $doc = $m['DOCUMENT'];
            $stats[] = Stat::make('Documents à valider', $doc['attente_validation']);
            $stats[] = Stat::make('Documents à approuver', $doc['attente_approbation']);
            $stats[] = Stat::make('Documents approuvés', $doc['approuve'])->color('success');
            $stats[] = Stat::make('Documents rejetés', $doc['rejete'])->color('danger');
        }

        $enCours = fn (string $code) => isset($m[$code]) ? $m[$code]['brouillon'] + $m[$code]['attente_validation'] + $m[$code]['attente_approbation'] : null;

        foreach ([
            'CONGE' => 'Congés en attente', 'SANCTION' => 'Sanctions en cours', 'EVALUATION' => 'Évaluations à effectuer',
            'PROMOTION' => 'Promotions en cours', 'MUTATION' => 'Mutations en cours',
        ] as $code => $libelle) {
            $total = $code === 'CONGE' && isset($m[$code])
                ? $m[$code]['attente_validation'] + $m[$code]['attente_approbation']
                : $enCours($code);

            if ($total !== null) {
                $stats[] = Stat::make($libelle, $total);
            }
        }

        if ($m !== []) {
            $stats[] = Stat::make('Dossiers en retard', $d['retards'])->color($d['retards'] > 0 ? 'danger' : 'success');
        }

        if ($d['paylist'] !== null) {
            $stats[] = Stat::make('Bulletins importés', $d['paylist']['bulletins']);
            $stats[] = Stat::make('Distributions réalisées', $d['paylist']['distributions_reussies'])->color('success');
            $stats[] = Stat::make('Distributions en échec', $d['paylist']['distributions_echec'])->color($d['paylist']['distributions_echec'] > 0 ? 'danger' : 'success');
        }

        return $stats;
    }
}
