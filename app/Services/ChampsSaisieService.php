<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Variables de modèle dont la valeur est saisie à la main (montants, dates de
 * demande, heures...). Un champ n'est exigé que si le modèle choisi l'utilise ;
 * il doit être renseigné avant soumission, approbation et génération du PDF.
 */
class ChampsSaisieService
{
    /** @var array<string, array{0: string, 1: string}> variable => [libellé, type number|date|time|text] */
    public const CHAMPS = [
        'SALAIRE_BRUT' => ['Salaire brut (FD)', 'number'],
        'MONTANT_SALAIRE' => ['Salaire mensuel (FD)', 'number'],
        'MONTANT_SAISIE_MANUELLE_1' => ['Indemnité de préavis (FD)', 'number'],
        'MONTANT_SAISIE_MANUELLE_2' => ['Indemnité de licenciement (FD)', 'number'],
        'MONTANT_SAISIE_MANUELLE_3' => ['Retenue retraite (FD)', 'number'],
        'MONTANT_SAISIE_MANUELLE_4' => ['Net à payer (FD)', 'number'],
        'DATE_DERNIER_BULLETIN_DE_PAIE' => ['Date du dernier bulletin de paie', 'date'],
        'ANNEE_CONGE' => ['Année du planning de congé', 'text'],
        'DATE_RETOUR' => ['Date réelle de retour', 'date'],
        'DATE_DEMANDE' => ['Date de la demande', 'date'],
        'JOURS_PRIS' => ['Nombre de jours déjà pris', 'number'],
        'JOURS_RESTANT' => ['Nombre de jours restant', 'number'],
        'HEURE_DEPART' => ['Heure de départ', 'time'],
        'HEURE_RETOUR' => ['Heure de retour', 'time'],
        'NOMBRE_HEURES' => ["Nombre d'heures", 'number'],
        'DATE_MOTIF' => ['Date des faits', 'date'],
        'SANCTION_SUIVANTE' => ['Sanction suivante', 'text'],
    ];

    /** @return list<string> champs saisis utilisés par le gabarit */
    public function requis(?string $template): array
    {
        if ($template === null) {
            return [];
        }

        preg_match_all('/\{\{\s*([^{}]*?)\s*\}\}/', $template, $m);

        return array_values(array_intersect(array_keys(self::CHAMPS), array_unique($m[1])));
    }

    /** @return list<string> champs requis non renseignés */
    public function manquants(Model $record): array
    {
        $valeurs = $record->donnees_saisies ?? [];

        return array_values(array_filter(
            $this->requis($record->modele?->contenu),
            fn (string $nom) => ($valeurs[$nom] ?? '') === ''
        ));
    }

    /** @throws WorkflowException */
    public function verifier(Model $record): void
    {
        $manquants = $this->manquants($record);

        if ($manquants !== []) {
            throw new WorkflowException('Champs à saisir avant de poursuivre : '
                .implode(', ', array_map(fn ($n) => self::CHAMPS[$n][0], $manquants)).'.');
        }
    }

    public function formater(string $nom, mixed $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '';
        }

        return match (self::CHAMPS[$nom][1] ?? 'text') {
            'number' => is_numeric($valeur)
                ? number_format((float) $valeur, fmod((float) $valeur, 1.0) === 0.0 ? 0 : 2, ',', ' ')
                : (string) $valeur,
            'date' => rescue(fn () => Carbon::parse($valeur)->format('d/m/Y'), (string) $valeur, false),
            default => (string) $valeur,
        };
    }
}
