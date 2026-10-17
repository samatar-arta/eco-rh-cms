<?php

namespace App\Enums;

enum DistributionStatut: string
{
    case EnAttente = 'EN_ATTENTE';
    case EnCours = 'EN_COURS';
    /** Remis au serveur de messagerie (ou en main propre) : ne prouve pas la lecture. */
    case Envoye = 'ENVOYE';
    case Echec = 'ECHEC';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::EnCours => 'En cours',
            self::Envoye => 'Envoyé',
            self::Echec => 'Échec',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EnAttente => 'gray',
            self::EnCours => 'info',
            self::Envoye => 'success',
            self::Echec => 'danger',
        };
    }
}
