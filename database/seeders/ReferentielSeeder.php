<?php

namespace Database\Seeders;

use App\Models\Contrat;
use App\Models\Societe;
use Illuminate\Database\Seeder;

/** Référentiels initiaux, idempotent (sans compte utilisateur ni mot de passe). */
class ReferentielSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['DLS', 'Djibouti Labour Services'],
            ['SGTD', 'SGTD'],
            ['DMP', 'DMP'],
            ['IDIPO', 'IDIPO'],
        ] as [$code, $name]) {
            Societe::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach ([
            'JOURNALIER_SGTD' => 'JOURNALIER SGTD',
            'JOURNALIER_DMP' => 'JOURNALIER DMP',
            'PERMANENT_DLS' => 'PERMANENT DLS',
            'PERMANENT_SGTD' => 'PERMANENT SGTD',
            'PERMANENT_DMP' => 'PERMANENT DMP',
            'FREE_ZONE_PERMANENT' => 'FREE ZONE-PERMANENT',
        ] as $code => $name) {
            Contrat::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
