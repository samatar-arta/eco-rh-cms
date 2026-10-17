<?php

namespace App\Services;

use App\Enums\RhModule;
use App\Models\ReferenceSequence;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Numérotation administrative : un compteur par module et par année, commun à
 * tous les types du module. Jamais déduit de l'identifiant technique.
 */
class ReferenceService
{
    private const TENTATIVES = 3;

    /**
     * Réserve le numéro suivant du module et renvoie la référence formatée.
     * La ligne de séquence est verrouillée (InnoDB) : pas de doublon sous charge.
     * Un numéro consommé n'est jamais rendu, même si le dossier est annulé.
     */
    public function next(RhModule $module, ?CarbonInterface $date = null): string
    {
        $annee = ($date ?? now())->year;

        // Les tentatives ne rejouent qu'une transaction de plus haut niveau :
        // elles couvrent le rare interblocage à la création d'une séquence.
        return DB::transaction(function () use ($module, $annee) {
            $sequence = $this->sequenceVerrouillee($module, $annee);
            $sequence->increment('dernier_numero');

            return $sequence->formater();
        }, self::TENTATIVES);
    }

    /**
     * Attribue la référence officielle à un enregistrement métier. Idempotent :
     * si une référence existe déjà (ex. reprise après échec du PDF), elle est
     * renvoyée telle quelle sans consommer de numéro.
     */
    public function assign(Model $record, ?CarbonInterface $date = null): string
    {
        /** @var RhModule $module */
        $module = $record::rhModule();

        return DB::transaction(function () use ($record, $module, $date) {
            $verrouille = $record::query()->lockForUpdate()->findOrFail($record->getKey());

            if ($verrouille->reference !== null) {
                $record->setAttribute('reference', $verrouille->reference);
                $record->syncOriginalAttribute('reference');

                return $verrouille->reference;
            }

            $reference = $this->next($module, $date);
            $verrouille->reference = $reference;
            $verrouille->save();

            $record->setAttribute('reference', $reference);
            $record->syncOriginalAttribute('reference');

            return $reference;
        }, self::TENTATIVES);
    }

    private function sequenceVerrouillee(RhModule $module, int $annee): ReferenceSequence
    {
        $requete = fn () => ReferenceSequence::query()
            ->where('module', $module->value)
            ->where('annee', $annee)
            ->lockForUpdate();

        // Lecture non verrouillante d'abord : évite de poser un verrou d'intervalle
        // sur une ligne inexistante (source d'interblocages entre transactions).
        $existe = ReferenceSequence::query()
            ->where('module', $module->value)->where('annee', $annee)->exists();

        if (! $existe) {
            // Le nouvel exercice reprend préfixe et format de la dernière année connue.
            $modele = ReferenceSequence::query()->where('module', $module->value)->orderByDesc('annee')->first();

            DB::table('reference_sequences')->insertOrIgnore([
                'module' => $module->value,
                'annee' => $annee,
                'dernier_numero' => 0,
                'prefixe' => $modele?->prefixe ?? $module->defaultPrefix(),
                'format' => $modele?->format ?? '{numero}/{prefixe}/{annee}',
                'largeur' => $modele?->largeur ?? 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $requete()->firstOrFail();
    }
}
