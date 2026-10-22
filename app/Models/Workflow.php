<?php

namespace App\Models;

use App\Enums\RhModule;
use App\Enums\Role;
use App\Enums\WorkflowStatut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class Workflow extends Model
{
    protected $fillable = [
        'workflowable_type', 'workflowable_id', 'cree_par', 'valide_par', 'approuve_par',
        'statut', 'date_soumission', 'date_validation', 'date_approbation',
        'date_limite_validation', 'date_limite_approbation', 'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'statut' => WorkflowStatut::class,
            'date_soumission' => 'datetime',
            'date_validation' => 'datetime',
            'date_approbation' => 'datetime',
            'date_limite_validation' => 'datetime',
            'date_limite_approbation' => 'datetime',
            'retard_notifie_le' => 'datetime',
        ];
    }

    public function workflowable(): MorphTo
    {
        return $this->morphTo();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }

    /** Dossiers dont l'échéance de l'étape en cours est dépassée. */
    public function scopeEnRetard(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(fn ($w) => $w->where('statut', WorkflowStatut::EnAttenteValidation->value)
                ->where('date_limite_validation', '<', now()))
                ->orWhere(fn ($w) => $w->where('statut', WorkflowStatut::EnAttenteApprobation->value)
                    ->where('date_limite_approbation', '<', now()));
        });
    }

    /**
     * Workflows visibles par l'utilisateur. ADMIN : tous. Sinon, toujours limité au périmètre
     * de l'employé concerné, puis par rôle : Manager = tous les statuts, Senior = dossiers en
     * attente de validation, Officier/Junior = ses propres créations. Les rôles s'additionnent.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->estAdministrateur()) {
            return $query;
        }

        $classes = collect(RhModule::cases())->map(fn (RhModule $m) => $m->recordClass())->all();
        $dansLePerimetre = fn (Builder $w) => $w->whereHasMorph(
            'workflowable',
            $classes,
            fn (Builder $r) => $r->whereHas('employe', fn (Builder $e) => $e->visibleTo($user))
        );

        $branches = [];
        if ($user->hasRole(Role::RhManager)) {
            $branches[] = fn (Builder $b) => $dansLePerimetre($b);
        }
        if ($user->hasRole(Role::RhSenior)) {
            $branches[] = fn (Builder $b) => $dansLePerimetre($b)->where('statut', WorkflowStatut::EnAttenteValidation->value);
        }
        if ($user->hasAnyRole(Role::RhOfficer, Role::RhJunior)) {
            $branches[] = fn (Builder $b) => $dansLePerimetre($b)->where('cree_par', $user->getKey());
        }

        if ($branches === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outer) use ($branches) {
            foreach ($branches as $branch) {
                $outer->orWhere($branch);
            }
        });
    }

    public function estEnRetard(): bool
    {
        return match ($this->statut) {
            WorkflowStatut::EnAttenteValidation => $this->date_limite_validation?->isPast() ?? false,
            WorkflowStatut::EnAttenteApprobation => $this->date_limite_approbation?->isPast() ?? false,
            default => false,
        };
    }

    /**
     * Chronologie pour l'affichage : acteur, rôle, étape, statut, commentaire,
     * durée depuis l'entrée précédente (minutes) et indicateur de retard.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function timeline(): Collection
    {
        $previous = null;

        return $this->histories()->with('user')->orderBy('date_action')->orderBy('id')->get()
            ->map(function (WorkflowHistory $entry) use (&$previous) {
                $row = [
                    'date_action' => $entry->date_action,
                    'utilisateur' => $entry->user?->name,
                    'role' => $entry->role,
                    'etape' => $entry->etape,
                    'statut' => $entry->statut,
                    'commentaire' => $entry->commentaire,
                    'duree_minutes' => $previous ? (int) $previous->date_action->diffInMinutes($entry->date_action, true) : null,
                    'en_retard' => $previous?->date_limite !== null && $entry->date_action->gt($previous->date_limite),
                ];
                $previous = $entry;

                return $row;
            });
    }
}
