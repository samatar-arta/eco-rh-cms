<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelEvaluation extends ModelTemplate
{
    protected $table = 'model_evaluations';

    protected $fillable = [
        'type_evaluation_id',
        'titre',
        'contenu',
        'criteres',
        'version',
        'is_default',
        'is_active',
        'cree_par',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'criteres' => 'array',
        ]);
    }

    public static function typeForeignKey(): string
    {
        return 'type_evaluation_id';
    }

    public static function typeClass(): string
    {
        return TypeEvaluation::class;
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'model_evaluation_id');
    }
}
