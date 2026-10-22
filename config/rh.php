<?php

return [
    'conges' => [
        // 'calendaires' : tous les jours ; 'ouvres' : hors jours non travaillés et fériés.
        'mode_calcul' => env('RH_CONGES_MODE', 'calendaires'),
        // Numéros ISO des jours non travaillés (1 = lundi ... 7 = dimanche).
        'jours_non_travailles' => [5, 6],
        // Jours fériés fixes (Y-m-d) à compléter par l'administration.
        'jours_feries' => [],
    ],

    'pdf' => [
        // Stockage privé : aucun accès public direct, téléchargement par route autorisée.
        'disk' => env('RH_PDF_DISK', 'local'),
        'directory' => 'dossiers',
    ],

    'notifications' => [
        // E-mail en plus de la notification interne (nécessite un serveur de messagerie configuré).
        'mail' => (bool) env('RH_NOTIFICATIONS_MAIL', false),
    ],

    'paylist' => [
        // Bulletins stockés sur disque privé : storage/app/private/paylists/<année>/<mois>/.
        'disk' => env('RH_PAYLIST_DISK', 'local'),
        'directory' => 'paylists',
        // Limites de l'archive (protection contre les archives piégées).
        'max_fichier_ko' => (int) env('RH_PAYLIST_MAX_FICHIER_KO', 5120),
        'max_entrees' => (int) env('RH_PAYLIST_MAX_ENTREES', 2000),
        'max_total_mo' => (int) env('RH_PAYLIST_MAX_TOTAL_MO', 200),
    ],

];
