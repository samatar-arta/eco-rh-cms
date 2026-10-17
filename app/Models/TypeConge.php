<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeConge extends Model
{
    protected $table = 'types_conges';

    protected $fillable = [
        'code',
        'name',
        'color',
        'default_days_per_year',
        'requires_justificatif',
        'requires_workflow',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_days_per_year' => 'integer',
            'requires_justificatif' => 'boolean',
            'requires_workflow' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function modeles(): HasMany
    {
        return $this->hasMany(ModelConge::class, 'type_conge_id');
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Conge::class, 'type_conge_id');
    }
}
