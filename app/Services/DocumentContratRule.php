<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Document;

/**
 * Cohérence type de document ↔ contrat de l'employé : l'attestation de mission
 * (AMT) est réservée aux journaliers, l'attestation de travail (AT) ne leur est
 * pas délivrée. Vérifiée côté serveur à la soumission et à l'approbation.
 */
class DocumentContratRule
{
    private const TYPE_TRAVAIL = 'AT';

    private const TYPE_MISSION = 'AMT';

    /** @throws WorkflowException */
    public function verifier(Document $document): void
    {
        $code = $document->type?->code;
        $contrat = $document->employe?->contrat;

        if ($contrat === null || ! in_array($code, [self::TYPE_TRAVAIL, self::TYPE_MISSION], true)) {
            return;
        }

        $journalier = str_starts_with((string) $contrat->code, 'JOURNALIER');

        if ($code === self::TYPE_MISSION && ! $journalier) {
            throw new WorkflowException('Modèle Mission réservé aux journaliers.');
        }

        if ($code === self::TYPE_TRAVAIL && $journalier) {
            throw new WorkflowException('Utilisez Attestation de mission pour les journaliers.');
        }
    }
}
