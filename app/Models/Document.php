<?php

namespace App\Models;

use App\Enums\RhModule;
use App\Models\Concerns\HasFrozenContent;
use App\Models\Concerns\HasImmutableReference;
use App\Models\Concerns\HasWorkflow;
use App\Models\Concerns\ValidatesTemplateConsistency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFrozenContent, HasImmutableReference, HasWorkflow, ValidatesTemplateConsistency;

    public static function rhModule(): RhModule
    {
        return RhModule::Document;
    }

    protected $table = 'documents';

    protected $fillable = [
        'matricule',
        'type_document_id',
        'model_document_id',
        'model_version',
        'cree_par',
        'reference',
        'date_production',
        'contenu_genere',
        'fichier_path',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'date_production' => 'date',
            'model_version' => 'integer',
        ];
    }

    protected function typeKey(): string
    {
        return 'type_document_id';
    }

    protected function templateKey(): string
    {
        return 'model_document_id';
    }

    protected function templateClass(): string
    {
        return ModelDocument::class;
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class, 'type_document_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelDocument::class, 'model_document_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
