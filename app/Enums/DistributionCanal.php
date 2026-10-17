<?php

namespace App\Enums;

enum DistributionCanal: string
{
    case Email = 'EMAIL';
    case Remise = 'REMISE';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Remise => 'Remise en main propre',
        };
    }
}
