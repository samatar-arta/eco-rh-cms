<?php

return [
    /*
    | Délais de traitement du workflow centralisé (en heures). Une échéance dépassée
    | est détectée par `php artisan workflow:check-delays` (planifiée toutes les heures).
    */
    'delai_validation_heures' => (int) env('RH_DELAI_VALIDATION_HEURES', 48),
    'delai_approbation_heures' => (int) env('RH_DELAI_APPROBATION_HEURES', 48),

    // Le créateur ne valide/approuve pas son dossier ; l'approbateur != validateur.
    'separation_des_taches' => (bool) env('RH_SEPARATION_DES_TACHES', true),
];
