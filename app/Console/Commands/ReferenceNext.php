<?php

namespace App\Console\Commands;

use App\Enums\RhModule;
use App\Services\ReferenceService;
use Illuminate\Console\Command;

class ReferenceNext extends Command
{
    protected $signature = 'references:next {module : DOCUMENT, CONGE, SANCTION, EVALUATION, PROMOTION ou MUTATION}';

    protected $description = 'Réserve et affiche la prochaine référence du module (outil de diagnostic : consomme un numéro)';

    public function handle(ReferenceService $references): int
    {
        $module = RhModule::tryFrom(strtoupper($this->argument('module')));

        if ($module === null) {
            $this->error('Module inconnu.');

            return self::FAILURE;
        }

        $this->line($references->next($module));

        return self::SUCCESS;
    }
}
