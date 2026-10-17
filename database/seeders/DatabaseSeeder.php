<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Aucun utilisateur n'est créé ici : le premier administrateur
     * s'obtient avec `php artisan app:create-admin`.
     */
    public function run(): void
    {
        $this->call([
            ReferentielSeeder::class,
            TypesEtModelesSeeder::class,
            ReferenceSequenceSeeder::class,
        ]);
    }
}
