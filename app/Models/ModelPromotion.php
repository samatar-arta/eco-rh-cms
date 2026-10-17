<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelPromotion extends ModelTemplate
{
    protected $table = 'model_promotions';

    protected $fillable = [
        'type_promotion_id',
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
        return 'type_promotion_id';
    }

    public static function typeClass(): string
    {
        return TypePromotion::class;
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Promotion::class, 'model_promotion_id');
    }
}
