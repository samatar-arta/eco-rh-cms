<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeDocument extends Model
{
    protected $table = 'types_documents';

    protected $fillable = [
        'code',
        'name',
        'description',
        'requires_model',
        'requires_workflow',
        'delai_renouvellement_mois',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_model' => 'boolean',
            'requires_workflow' => 'boolean',
            'delai_renouvellement_mois' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function modeles(): HasMany
    {
        return $this->hasMany(ModelDocument::class, 'type_document_id');
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Document::class, 'type_document_id');
    }
}
