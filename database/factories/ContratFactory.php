<?php

namespace Database\Factories;

use App\Models\Contrat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contrat> */
class ContratFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('CT_????')),
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
