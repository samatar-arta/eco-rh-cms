<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Une référence attribuée ne change plus et n'est jamais libérée : on peut
 * la poser (null -> valeur) mais ni la modifier, ni supprimer l'enregistrement.
 *
 * @mixin Model
 */
trait HasImmutableReference
{
    protected static function bootHasImmutableReference(): void
    {
        static::updating(function (Model $record) {
            if ($record->isDirty('reference') && $record->getOriginal('reference') !== null) {
                throw new LogicException('Une référence attribuée est immuable.');
            }
        });

        static::deleting(function (Model $record) {
            if ($record->getOriginal('reference') !== null) {
                throw new LogicException('Un enregistrement portant une référence officielle ne peut pas être supprimé.');
            }
        });
    }
}
