<?php

namespace App\Enums;

enum StatutEmploye: string
{
    case Actif = 'ACTIF';
    case Suspendu = 'SUSPENDU';
    case Sorti = 'SORTI';
}
