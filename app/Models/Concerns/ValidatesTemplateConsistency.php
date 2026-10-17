<?php

namespace App\Models\Concerns;

use App\Models\ModelTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Garde côté serveur : le modèle choisi doit appartenir au type choisi,
 * quelle que soit la valeur envoyée par le navigateur.
 *
 * @mixin Model
 */
trait ValidatesTemplateConsistency
{
    /** Colonne du type (ex. type_document_id). */
    abstract protected function typeKey(): string;

    /** Colonne du modèle (ex. model_document_id). */
    abstract protected function templateKey(): string;

    /** @return class-string<ModelTemplate> */
    abstract protected function templateClass(): string;

    protected static function bootValidatesTemplateConsistency(): void
    {
        static::saving(function (Model $record) {
            $typeKey = $record->typeKey();
            $templateKey = $record->templateKey();

            if ($record->{$templateKey} === null) {
                return;
            }

            if ($record->exists && ! $record->isDirty([$typeKey, $templateKey])) {
                return;
            }

            $template = $record->templateClass()::find($record->{$templateKey});

            if ($template === null || (int) $template->{$typeKey} !== (int) $record->{$typeKey}) {
                throw ValidationException::withMessages([
                    $templateKey => 'Le modèle sélectionné ne correspond pas au type choisi.',
                ]);
            }
        });
    }
}
