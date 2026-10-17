<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelConge extends ModelTemplate
{
    protected $table = 'model_conges';

    protected $fillable = [
        'type_conge_id',
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
        return 'type_conge_id';
    }

    public static function typeClass(): string
    {
        return TypeConge::class;
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Conge::class, 'model_conge_id');
    }
}
