<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelSanction extends ModelTemplate
{
    protected $table = 'model_sanctions';

    protected $fillable = [
        'type_sanction_id',
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
        return 'type_sanction_id';
    }

    public static function typeClass(): string
    {
        return TypeSanction::class;
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Sanction::class, 'model_sanction_id');
    }
}
