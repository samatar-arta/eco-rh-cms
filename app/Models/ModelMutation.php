<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelMutation extends ModelTemplate
{
    protected $table = 'model_mutations';

    protected $fillable = [
        'type_mutation_id',
        'titre',
        'contenu',
        'version',
        'is_default',
        'is_active',
        'cree_par',
    ];

    protected function casts(): array
    {
        return parent::casts();
    }

    public static function typeForeignKey(): string
    {
        return 'type_mutation_id';
    }

    public static function typeClass(): string
    {
        return TypeMutation::class;
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Mutation::class, 'model_mutation_id');
    }
}
