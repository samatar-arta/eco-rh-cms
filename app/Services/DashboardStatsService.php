<?php

namespace App\Services;

use App\Enums\DistributionStatut;
use App\Enums\RhModule;
use App\Enums\Role;
use App\Enums\WorkflowStatut;
use App\Models\Employe;
use App\Models\Paylist;
use App\Models\PaylistDistribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indicateurs du tableau de bord. Tout est calculé à partir de requêtes
 * déjà restreintes au périmètre et aux droits de l'utilisateur : aucune
 * statistique sur des données qu'il ne peut pas consulter.
 */
class DashboardStatsService
{
    /**
     * @return array{
     *   employes:int, par_societe:array<string,int>, par_contrat:array<string,int>,
     *   modules:array<string,array<string,int>>, retards:int, paylist:?array<string,int>
     * }
     */
    public function pour(User $user): array
    {
        $employes = Employe::query()->visibleTo($user)->where('statut_employe', 'ACTIF');

        $modules = [];
        $retards = 0;

        foreach (RhModule::cases() as $module) {
            $classe = $module->recordClass();

            if (! $user->can('viewAny', $classe)) {
                continue;
            }

            $base = fn (): Builder => $this->visibles($classe::query(), $module, $user);
            $statut = fn (WorkflowStatut ...$s) => $base()->whereHas('workflow', fn ($q) => $q->whereIn('statut', array_map(fn ($x) => $x->value, $s)))->count();

            $modules[$module->value] = [
                'brouillon' => $base()->where(fn ($q) => $q->whereDoesntHave('workflow')
                    ->orWhereHas('workflow', fn ($w) => $w->where('statut', WorkflowStatut::Brouillon->value)))->count(),
                'attente_validation' => $statut(WorkflowStatut::DocumentSoumis, WorkflowStatut::EnAttenteValidation),
                'attente_approbation' => $statut(WorkflowStatut::Valide, WorkflowStatut::EnAttenteApprobation),
                'approuve' => $statut(WorkflowStatut::Approuve),
                'rejete' => $statut(WorkflowStatut::Rejete),
            ];

            $retards += $base()->whereHas('workflow', fn ($q) => $q->enRetard())->count();
        }

        $paylist = null;

        if ($user->can('viewAny', Paylist::class)) {
            $paylist = [
                'bulletins' => Paylist::count(),
                'distributions_reussies' => PaylistDistribution::where('statut', DistributionStatut::Envoye->value)->count(),
                'distributions_echec' => PaylistDistribution::where('statut', DistributionStatut::Echec->value)->count(),
            ];
        }

        return [
            'employes' => (clone $employes)->count(),
            'par_societe' => $this->repartition($employes, 'societes', 'societe_id'),
            'par_contrat' => $this->repartition($employes, 'contrats', 'contrat_id'),
            'modules' => $modules,
            'retards' => $retards,
            'paylist' => $paylist,
        ];
    }

    /** Enregistrements du module dans le périmètre ; sanctions : accès renforcé. */
    private function visibles(Builder $query, RhModule $module, User $user): Builder
    {
        $query->whereHas('employe', fn (Builder $q) => $q->visibleTo($user));

        if ($module === RhModule::Sanction && ! $user->hasAnyRole(Role::RhManager, Role::RhSenior, Role::RhOfficer)) {
            $query->where('cree_par', $user->getKey());
        }

        return $query;
    }

    /** @return array<string,int> */
    private function repartition(Builder $employes, string $table, string $cle): array
    {
        return (clone $employes)
            ->join($table, "$table.id", '=', "employes.$cle")
            ->selectRaw("$table.name as libelle, count(*) as total")
            ->groupBy("$table.id", "$table.name")
            ->orderByDesc('total')
            ->pluck('total', 'libelle')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
