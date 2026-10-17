<?php

namespace App\Models;

use App\Enums\RhModule;
use App\Models\Concerns\HasFrozenContent;
use App\Models\Concerns\HasImmutableReference;
use App\Models\Concerns\HasWorkflow;
use App\Models\Concerns\ValidatesTemplateConsistency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    use HasFrozenContent, HasImmutableReference, HasWorkflow, ValidatesTemplateConsistency;

    public static function rhModule(): RhModule
    {
        return RhModule::Promotion;
    }

    protected $table = 'promotions';

    protected $fillable = [
        'matricule',
        'type_promotion_id',
        'model_promotion_id',
        'cree_par',
        'ancienne_fonction',
        'nouvelle_fonction',
        'ancien_grade',
        'nouveau_grade',
        'ancien_salaire',
        'nouveau_salaire',
        'date_effet',
        'motif',
        'reference',
        'contenu_genere',
        'model_version',
        'fichier_path',
    ];

    /** @var list<string> */
    protected $hidden = [
        'ancien_salaire',
        'nouveau_salaire',
    ];

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
            'effet_applique_le' => 'datetime',
            'ancien_salaire' => 'decimal:2',
            'nouveau_salaire' => 'decimal:2',
        ];
    }

    protected function typeKey(): string
    {
        return 'type_promotion_id';
    }

    protected function templateKey(): string
    {
        return 'model_promotion_id';
    }

    protected function templateClass(): string
    {
        return ModelPromotion::class;
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypePromotion::class, 'type_promotion_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelPromotion::class, 'model_promotion_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
