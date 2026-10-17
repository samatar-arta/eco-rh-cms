<?php

namespace Database\Seeders;

use App\Enums\RhModule;
use App\Models\ReferenceSequence;
use Illuminate\Database\Seeder;

/** Pré-crée les séquences de l'année en cours (évite la création concurrente en production). */
class ReferenceSequenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RhModule::cases() as $module) {
            ReferenceSequence::firstOrCreate(
                ['module' => $module->value, 'annee' => now()->year],
                ['prefixe' => $module->defaultPrefix()]
            );
        }
    }
}
