<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Jamais d'écriture dans le stockage réel pendant les tests.
        Storage::fake('local');

        // Filament/Livewire règlent la vue de pagination par défaut (statique) : on évite qu'elle fuite d'un test à l'autre.
        Paginator::defaultView('pagination::tailwind');
    }
}
