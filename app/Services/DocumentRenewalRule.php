<?php

namespace App\Services;

use App\Enums\WorkflowStatut;
use App\Exceptions\WorkflowException;
use App\Models\Document;

/**
 * Délai minimal entre deux productions officielles d'un même type de
 * document pour un même employé. Appliqué seulement si le type porte un
 * `delai_renouvellement_mois`.
 */
class DocumentRenewalRule
{
    /** @throws WorkflowException */
    public function verifier(Document $document): void
    {
        $mois = $document->type?->delai_renouvellement_mois;

        if (! $mois) {
            return;
        }

        $derniere = Document::query()
            ->where('matricule', $document->matricule)
            ->where('type_document_id', $document->type_document_id)
            ->whereKeyNot($document->getKey())
            ->whereNotNull('reference')
            ->whereHas('workflow', fn ($w) => $w
                ->where('statut', WorkflowStatut::Approuve->value)
                ->where('date_approbation', '>', now()->subMonths($mois)))
            ->with('workflow')
            ->get()
            ->sortByDesc(fn (Document $d) => $d->workflow->date_approbation)
            ->first();

        if ($derniere === null) {
            return;
        }

        $date = $derniere->workflow->date_approbation;

        throw new WorkflowException(sprintf(
            'Un document « %s » a déjà été produit pour cet employé le %s (%s). Délai minimal : %d mois ; nouvelle production possible à partir du %s.',
            $document->type->name,
            $date->format('d/m/Y'),
            $derniere->reference,
            $mois,
            $date->copy()->addMonths($mois)->format('d/m/Y'),
        ));
    }
}
