<?php

namespace Database\Factories;

use App\Models\Departement;
use App\Models\SiteAffectation;
use App\Models\SiteDepartement;
use App\Models\Societe;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteDepartement> */
class SiteDepartementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => SiteAffectation::factory(),
            'societe_id' => Societe::factory(),
            'departement_id' => fn (array $attrs) => Departement::factory()
                ->create(['societe_id' => $attrs['societe_id']])->id,
        ];
    }
}
