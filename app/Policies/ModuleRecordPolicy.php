<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\WorkflowStatut;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Autorisations communes des six modules RH. Le périmètre de l'employé est
 * toujours contrôlé ; les actions de workflow suivent le statut courant.
 * Le WorkflowService revérifie tout au moment de l'action.
 */
abstract class ModuleRecordPolicy
{
    /** Rôles autorisés à consulter les enregistrements du module. */
    protected function rolesLecture(): array
    {
        return Role::rh();
    }

    /** @var class-string<Model> */
    protected string $modelClass;

    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(...$this->rolesLecture());
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user) && $this->dansLePerimetre($user, $record);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(...($this->modelClass)::rhModule()->creationRoles());
    }

    public function update(User $user, Model $record): bool
    {
        return $this->create($user)
            && $this->dansLePerimetre($user, $record)
            && $this->modifiable($record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record)
            && $record->reference === null
            && ($record->workflow?->statut ?? WorkflowStatut::Brouillon) === WorkflowStatut::Brouillon;
    }

    public function soumettre(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }

    public function valider(User $user, Model $record): bool
    {
        return $this->peutTraiter($user, $record, Role::RhSenior, WorkflowStatut::EnAttenteValidation);
    }

    public function approuver(User $user, Model $record): bool
    {
        return $this->peutTraiter($user, $record, Role::RhManager, WorkflowStatut::EnAttenteApprobation);
    }

    /** Rejet : Senior en validation, Manager en approbation. */
    public function rejeter(User $user, Model $record): bool
    {
        return $this->valider($user, $record) || $this->approuver($user, $record);
    }

    /** Relance de la génération PDF : Manager RH, dossier approuvé sans fichier final. */
    public function regenerer(User $user, Model $record): bool
    {
        return $user->is_active
            && $user->hasRole(Role::RhManager)
            && $record->reference !== null
            && $record->{$record->colonneFichierFinal()} === null
            && $record->workflow?->statut === WorkflowStatut::Approuve
            && $this->dansLePerimetre($user, $record);
    }

    /** Le PDF final se télécharge comme le dossier se consulte, une fois archivé. */
    public function telecharger(User $user, Model $record): bool
    {
        return $record->{$record->colonneFichierFinal()} !== null && $this->view($user, $record);
    }

    protected function peutTraiter(User $user, Model $record, Role $role, WorkflowStatut $statut): bool
    {
        $workflow = $record->workflow;

        if (! $user->is_active || ! $user->hasRole($role) || $workflow?->statut !== $statut) {
            return false;
        }

        if (config('rh.workflow.separation_des_taches')
            && ($workflow->cree_par === $user->getKey() || $workflow->valide_par === $user->getKey())) {
            return false;
        }

        return $this->dansLePerimetre($user, $record);
    }

    /** Brouillon, rejeté ou expiré : modifiable ; sinon figé dans le workflow. */
    protected function modifiable(Model $record): bool
    {
        $statut = $record->workflow?->statut ?? WorkflowStatut::Brouillon;

        return in_array($statut, [WorkflowStatut::Brouillon, WorkflowStatut::Rejete, WorkflowStatut::Expire], true);
    }

    protected function dansLePerimetre(User $user, Model $record): bool
    {
        return (bool) $record->employe?->isInScopeOf($user);
    }
}
