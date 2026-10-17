<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'ADMIN';
    case RhManager = 'RH_MANAGER';
    case RhSenior = 'RH_SENIOR';
    case RhOfficer = 'RH_OFFICER';
    case RhJunior = 'RH_JUNIOR';
    case GestionnairePaie = 'GESTIONNAIRE_PAIE';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::RhManager => 'Manager RH',
            self::RhSenior => 'Senior RH',
            self::RhOfficer => 'Officier RH',
            self::RhJunior => 'Junior RH',
            self::GestionnairePaie => 'Gestionnaire de paie',
        };
    }

    /** @return list<self> */
    public static function rh(): array
    {
        return [self::RhManager, self::RhSenior, self::RhOfficer, self::RhJunior];
    }
}
