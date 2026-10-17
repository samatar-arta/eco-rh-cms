<?php

namespace App\Enums;

enum PaylistImportStatut: string
{
    case Analyse = 'ANALYSE';
    case Importe = 'IMPORTE';
    case ImporteAvecErreurs = 'IMPORTE_AVEC_ERREURS';
    case Echec = 'ECHEC';

    public function label(): string
    {
        return match ($this) {
            self::Analyse => 'Analysé (en attente de confirmation)',
            self::Importe => 'Importé',
            self::ImporteAvecErreurs => 'Importé avec erreurs',
            self::Echec => 'Échec',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Analyse => 'gray',
            self::Importe => 'success',
            self::ImporteAvecErreurs => 'warning',
            self::Echec => 'danger',
        };
    }

    /** Une confirmation (ou reprise) reste possible. */
    public function confirmable(): bool
    {
        return in_array($this, [self::Analyse, self::ImporteAvecErreurs, self::Echec], true);
    }
}
