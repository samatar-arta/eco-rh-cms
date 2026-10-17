<?php

namespace Database\Factories;

use App\Models\Departement;
use App\Models\Employe;
use App\Models\SiteAffectation;
use App\Models\Societe;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employe> */
class EmployeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'matricule' => fake()->unique()->numerify('MAT-#####'),
            'name' => fake()->name(),
            'fonction' => fake()->jobTitle(),
            'date_naissance' => fake()->dateTimeBetween('-55 years', '-20 years'),
            'date_embauche' => fake()->dateTimeBetween('-10 years', '-1 month'),
            'statut_employe' => 'ACTIF',
            'societe_id' => Societe::factory(),
            // Le département suit la société de l'employé (contrainte composite).
            'departement_id' => fn (array $attrs) => Departement::factory()
                ->create(['societe_id' => $attrs['societe_id']])->id,
            'site_id' => SiteAffectation::factory(),
        ];
    }
}
