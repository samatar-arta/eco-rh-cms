<?php

namespace App\Jobs;

use App\Models\PaylistDistribution;
use App\Services\PaylistDistributionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envoi d'un bulletin à un contact. Une seule tentative automatique : un échec
 * est enregistré (statut ECHEC) et relancé explicitement par le gestionnaire.
 */
class EnvoyerBulletinJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $distributionId) {}

    public function handle(PaylistDistributionService $service): void
    {
        $distribution = PaylistDistribution::find($this->distributionId);

        if ($distribution !== null) {
            $service->envoyer($distribution);
        }
    }
}
