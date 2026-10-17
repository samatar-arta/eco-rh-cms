<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Societe;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'societe_id' => Societe::factory(),
            'is_active' => true,
        ];
    }
}
