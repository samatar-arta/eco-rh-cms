<?php

namespace App\Models;

use App\Enums\RhModule;
use App\Models\Concerns\HasFrozenContent;
use App\Models\Concerns\HasImmutableReference;
use App\Models\Concerns\HasWorkflow;
use App\Models\Concerns\ValidatesTemplateConsistency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mutation extends Model
{
    use HasFrozenContent, HasImmutableReference, HasWorkflow, ValidatesTemplateConsistency;

    public static function rhModule(): RhModule
    {
        return RhModule::Mutation;
    }

    protected $table = 'mutations';

    protected $fillable = [
        'matricule',
        'type_mutation_id',
        'model_mutation_id',
        'ancien_site_id',
        'nouveau_site_id',
        'ancien_departement_id',
        'nouveau_departement_id',
        'ancienne_societe_id',
        'nouvelle_societe_id',
        'ancienne_fonction',
        'nouvelle_fonction',
        'date_effet',
        'motif',
        'cree_par',
        'reference',
        'contenu_genere',
        'model_version',
        'fichier_path',
    ];

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
            'effet_applique_le' => 'datetime',
        ];
    }

    protected function typeKey(): string
    {
        return 'type_mutation_id';
    }

    protected function templateKey(): string
    {
        return 'model_mutation_id';
    }

    protected function templateClass(): string
    {
        return ModelMutation::class;
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeMutation::class, 'type_mutation_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelMutation::class, 'model_mutation_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function ancienSite(): BelongsTo
    {
        return $this->belongsTo(SiteAffectation::class, 'ancien_site_id');
    }

    public function nouveauSite(): BelongsTo
    {
        return $this->belongsTo(SiteAffectation::class, 'nouveau_site_id');
    }

    public function ancienDepartement(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'ancien_departement_id');
    }

    public function nouveauDepartement(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'nouveau_departement_id');
    }

    public function ancienneSociete(): BelongsTo
    {
        return $this->belongsTo(Societe::class, 'ancienne_societe_id');
    }

    public function nouvelleSociete(): BelongsTo
    {
        return $this->belongsTo(Societe::class, 'nouvelle_societe_id');
    }
}
