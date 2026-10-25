<?php

namespace App\Models;

use App\Enums\RhModule;
use App\Models\Concerns\HasFrozenContent;
use App\Models\Concerns\HasImmutableReference;
use App\Models\Concerns\HasWorkflow;
use App\Models\Concerns\ValidatesTemplateConsistency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Conge extends Model
{
    use HasFrozenContent, HasImmutableReference, HasWorkflow, ValidatesTemplateConsistency;

    public static function rhModule(): RhModule
    {
        return RhModule::Conge;
    }

    protected $table = 'conges';

    protected $fillable = [
        'matricule',
        'user_id',
        'type_conge_id',
        'model_conge_id',
        'date_debut',
        'date_fin',
        'nbre_jours',
        'motif',
        'fichier_path',
        'reference',
        'contenu_genere',
        'model_version',
        'decision_path',
        'solde_avant',
        'date_decision',
        'commentaire_decision',
        'donnees_saisies',
    ];

    protected function casts(): array
    {
        return [
            'donnees_saisies' => 'array',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'date_decision' => 'date',
            'nbre_jours' => 'decimal:1',
            'solde_avant' => 'decimal:1',
        ];
    }

    protected function typeKey(): string
    {
        return 'type_conge_id';
    }

    protected function templateKey(): string
    {
        return 'model_conge_id';
    }

    protected function templateClass(): string
    {
        return ModelConge::class;
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeConge::class, 'type_conge_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelConge::class, 'model_conge_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $conge) {
            if ($conge->date_debut && $conge->date_fin && $conge->date_fin->lt($conge->date_debut)) {
                throw ValidationException::withMessages([
                    'date_fin' => 'La date de fin doit être postérieure ou égale à la date de début.',
                ]);
            }
        });
    }
}
