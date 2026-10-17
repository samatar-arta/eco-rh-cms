<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeMutation extends Model
{
    protected $table = 'types_mutations';

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
        return $this->hasMany(ModelMutation::class, 'type_mutation_id');
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Mutation::class, 'type_mutation_id');
    }
}
