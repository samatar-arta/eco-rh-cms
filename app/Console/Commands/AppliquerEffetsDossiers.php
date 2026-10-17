<?php

namespace App\Console\Commands;

use App\Services\EffetsApprobationService;
use Illuminate\Console\Command;

class AppliquerEffetsDossiers extends Command
{
    protected $signature = 'rh:appliquer-effets';

    protected $description = 'Applique à l\'employé les promotions et mutations approuvées dont la date d\'effet est arrivée';

    public function handle(EffetsApprobationService $service): int
    {
        $r = $service->appliquerEchus();
        $this->info("{$r['appliques']} dossier(s) appliqué(s), {$r['conflits']} en conflit.");

        return $r['conflits'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
