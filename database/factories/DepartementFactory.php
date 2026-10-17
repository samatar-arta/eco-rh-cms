<?php

namespace Database\Factories;

use App\Models\Departement;
use App\Models\Societe;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Departement> */
class DepartementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'abrege' => strtoupper(fake()->lexify('???')),
            'societe_id' => Societe::factory(),
            'is_active' => true,
        ];
    }
}
