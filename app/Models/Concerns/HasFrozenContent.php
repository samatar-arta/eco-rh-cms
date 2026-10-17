<?php

namespace App\Models\Concerns;

use App\Enums\RhModule;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Une fois le contenu final généré (`contenu_genere`), le document est figé :
 * ni le contenu, ni la version du modèle, ni le dossier concerné ne changent ;
 * le fichier final ne peut être posé qu'une fois (null -> valeur).
 *
 * @mixin Model
 */
trait HasFrozenContent
{
    protected static function bootHasFrozenContent(): void
    {
        static::updating(function (Model $record) {
            if ($record->getOriginal('contenu_genere') === null) {
                return;
            }

            $figes = ['contenu_genere', 'model_version', 'matricule', $record->typeKey(), $record->templateKey()];
            if ($record->isDirty($figes)) {
                throw new LogicException('Un document finalisé est immuable.');
            }

            $fichier = $record->colonneFichierFinal();
            if ($record->isDirty($fichier) && $record->getOriginal($fichier) !== null) {
                throw new LogicException('Le fichier final ne peut plus être remplacé.');
            }
        });
    }

    /** Colonne portant le PDF final (le justificatif d'un congé a sa propre colonne). */
    public function colonneFichierFinal(): string
    {
        return static::rhModule() === RhModule::Conge ? 'decision_path' : 'fichier_path';
    }
}
