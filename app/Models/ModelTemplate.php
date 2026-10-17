<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Base des bibliothèques de modèles HTML (une table par module). Chaque modèle
 * est rattaché à un seul type du module ; un seul modèle par défaut par type ;
 * la version s'incrémente à chaque changement de contenu.
 */
abstract class ModelTemplate extends Model
{
    /** Colonne portant la clé étrangère vers le type du module. */
    abstract public static function typeForeignKey(): string;

    /** @return class-string<Model> */
    abstract public static function typeClass(): string;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $template) {
            if ($template->isDirty('contenu')) {
                $template->version = $template->getOriginal('version') + 1;
            }
        });

        static::saved(function (self $template) {
            if ($template->is_default) {
                static::query()
                    ->where(static::typeForeignKey(), $template->{static::typeForeignKey()})
                    ->whereKeyNot($template->getKey())
                    ->update(['is_default' => false]);
            }
        });
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(static::typeClass(), static::typeForeignKey());
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
