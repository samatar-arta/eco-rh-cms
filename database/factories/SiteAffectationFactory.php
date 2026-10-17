<?php

namespace Database\Factories;

use App\Models\SiteAffectation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteAffectation> */
class SiteAffectationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Site '.fake()->unique()->city(),
            'is_active' => true,
        ];
    }
}
