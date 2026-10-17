<?php

namespace App\Models;

use App\Enums\RhModule;
use App\Enums\Role;
use App\Models\Concerns\HasFrozenContent;
use App\Models\Concerns\HasImmutableReference;
use App\Models\Concerns\HasWorkflow;
use App\Models\Concerns\ValidatesTemplateConsistency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Evaluation extends Model
{
    use HasFrozenContent, HasImmutableReference, HasWorkflow, ValidatesTemplateConsistency;

    public static function rhModule(): RhModule
    {
        return RhModule::Evaluation;
    }

    protected $table = 'evaluations';

    protected $fillable = [
        'matricule',
        'type_evaluation_id',
        'model_evaluation_id',
        'evaluateur_id',
        'date_evaluation',
        'periode_debut',
        'periode_fin',
        'resultats_criteres',
        'note',
        'appreciation',
        'objectifs',
        'reference',
        'contenu_genere',
        'model_version',
        'fichier_path',
    ];

    protected function casts(): array
    {
        return [
            'date_evaluation' => 'date',
            'periode_debut' => 'date',
            'periode_fin' => 'date',
            'resultats_criteres' => 'array',
            'note' => 'decimal:2',
        ];
    }

    protected function typeKey(): string
    {
        return 'type_evaluation_id';
    }

    protected function templateKey(): string
    {
        return 'model_evaluation_id';
    }

    protected function templateClass(): string
    {
        return ModelEvaluation::class;
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeEvaluation::class, 'type_evaluation_id');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModelEvaluation::class, 'model_evaluation_id');
    }

    public function evaluateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluateur_id');
    }

    protected static function booted(): void
    {
        // La note finale est toujours recalculée côté serveur dès que des critères sont notés.
        static::saving(function (self $evaluation) {
            if (! empty($evaluation->resultats_criteres) && $evaluation->modele !== null) {
                $evaluation->note = $evaluation->calculerNote();
            }
        });

        static::saving(function (self $evaluation) {
            if ($evaluation->evaluateur_id === null || ! $evaluation->isDirty('evaluateur_id')) {
                return;
            }

            $evaluateur = User::find($evaluation->evaluateur_id);

            if (! $evaluateur?->hasAnyRole(Role::RhManager, Role::RhSenior)) {
                throw ValidationException::withMessages([
                    'evaluateur_id' => "L'évaluateur doit avoir le rôle Manager RH ou Senior RH.",
                ]);
            }
        });
    }

    /**
     * Note finale sur 20, pondérée par les coefficients des critères du modèle
     * (chaque note est ramenée à son `note_max`). Null si aucun critère noté.
     */
    public function calculerNote(): ?float
    {
        $criteres = collect($this->modele?->criteres ?? [])->keyBy('libelle');
        $total = 0.0;
        $poids = 0.0;

        foreach ($this->resultats_criteres ?? [] as $resultat) {
            $critere = $criteres->get($resultat['libelle'] ?? null);

            if ($critere === null) {
                continue;
            }

            $max = (float) ($critere['note_max'] ?? 20);
            $coefficient = (float) ($critere['coefficient'] ?? 1);
            $note = min(max((float) ($resultat['note'] ?? 0), 0), $max);

            $total += ($note / $max) * 20 * $coefficient;
            $poids += $coefficient;
        }

        return $poids > 0 ? round($total / $poids, 2) : null;
    }
}
