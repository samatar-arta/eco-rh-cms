<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypePromotion extends Model
{
    protected $table = 'types_promotions';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function modeles(): HasMany
    {
        return $this->hasMany(ModelPromotion::class, 'type_promotion_id');
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Promotion::class, 'type_promotion_id');
    }
}
