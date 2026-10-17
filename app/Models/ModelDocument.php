<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelDocument extends ModelTemplate
{
    protected $table = 'model_documents';

    protected $fillable = [
        'type_document_id',
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
        return 'type_document_id';
    }

    public static function typeClass(): string
    {
        return TypeDocument::class;
    }

    public function enregistrements(): HasMany
    {
        return $this->hasMany(Document::class, 'model_document_id');
    }
}
