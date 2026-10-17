<?php

namespace App\Enums;

enum WorkflowStatut: string
{
    case Brouillon = 'BROUILLON';
    case DocumentSoumis = 'DOCUMENT_SOUMIS';
    case EnAttenteValidation = 'EN_ATTENTE_VALIDATION';
    case Valide = 'VALIDE';
    case EnAttenteApprobation = 'EN_ATTENTE_APPROBATION';
    case Approuve = 'APPROUVE';
    case Rejete = 'REJETE';
    case Expire = 'EXPIRE';

    /** @return list<self> */
    public function transitionsAutorisees(): array
    {
        return match ($this) {
            self::Brouillon => [self::DocumentSoumis],
            self::DocumentSoumis => [self::EnAttenteValidation],
            self::EnAttenteValidation => [self::Valide, self::Rejete, self::Expire],
            self::Valide => [self::EnAttenteApprobation],
            self::EnAttenteApprobation => [self::Approuve, self::Rejete, self::Expire],
            self::Rejete, self::Expire => [self::Brouillon],
            self::Approuve => [],
        };
    }

    public function peutPasserA(self $cible): bool
    {
        return in_array($cible, $this->transitionsAutorisees(), true);
    }
}
