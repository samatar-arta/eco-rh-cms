<?php

namespace App\Services;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/** Nombre de jours d'un congé selon la règle configurée (config/rh.php). */
class CongeDayCounter
{
    public function count(CarbonInterface $debut, CarbonInterface $fin, ?string $mode = null): float
    {
        if ($fin->lt($debut)) {
            throw new InvalidArgumentException('La date de fin précède la date de début.');
        }

        $mode ??= config('rh.conges.mode_calcul');
        $jours = 0;

        for ($jour = $debut->copy()->startOfDay(); $jour->lte($fin); $jour->addDay()) {
            if ($mode === 'calendaires' || $this->estOuvre($jour)) {
                $jours++;
            }
        }

        return (float) $jours;
    }

    private function estOuvre(CarbonInterface $jour): bool
    {
        return ! in_array($jour->dayOfWeekIso, config('rh.conges.jours_non_travailles'), true)
            && ! in_array($jour->toDateString(), config('rh.conges.jours_feries'), true);
    }
}
