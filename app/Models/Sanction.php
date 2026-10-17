<?php

namespace App\Models;

use App\Enums\RhModule;
use App\Models\Concerns\HasFrozenContent;
use App\Models\Concerns\HasImmutableReference;
use App\Models\Concerns\HasWorkflow;
use App\Models\Concerns\ValidatesTemplateConsistency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sanction extends Model
{
    use HasFrozenContent, HasImmutableReference, HasWorkflow, ValidatesTemplateConsistency;

    public static function rhModule(): RhModule
    {
        return RhModule::Sanction;
    }

    protected $table = 'sanctions';

    protected $fillable = [
        'matricule',
        'type_sanction_id',
        'model_sanction_id',
        'cree_par',
        'date_sanction',
        'description',
        'motif',
        'sanction_precedente_id',
        'niveau_sanction_id',
        'reference',
        'contenu_genere',
        'model_version',
        'fichier_path',
    ];

    protected function casts(): array
    {
        return [
            'date_sanction' => 'date',
        ];
    }

    protected function typeKey(): string
    {
        return 'type_sanction_id';
    }

    protected function templateKey(): string
    {
        return 'model_sanction_id';
    }

    protected function templateClass(): string
    {
        return ModelSanction::class;
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeSanction::class, 'type_sanction_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelSanction::class, 'model_sanction_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function sanctionPrecedente(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sanction_precedente_id');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(NiveauSanction::class, 'niveau_sanction_id');
    }
}
