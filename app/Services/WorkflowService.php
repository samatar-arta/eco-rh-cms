<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\WorkflowStatut;
use App\Events\DossierApprouve;
use App\Exceptions\WorkflowException;
use App\Models\Document;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowHistory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Moteur de workflow centralisé : BROUILLON → soumis → validation (Senior RH)
 * → approbation (Manager RH) → référence officielle. Chaque action revérifie
 * le rôle, le périmètre et le statut sous verrou ; l'interface n'est jamais
 * la seule barrière.
 */
class WorkflowService
{
    public function __construct(
        private ReferenceService $references,
        private DocumentRenewalRule $renouvellement,
        private EffetsApprobationService $effets,
        private AuditService $audit,
        private NotificationService $notifier,
    ) {}

    /** Crée (une seule fois) le workflow BROUILLON d'un enregistrement. */
    public function ouvrir(Model $record, User $acteur): Workflow
    {
        $this->exigerRole($acteur, $record::rhModule()->creationRoles(), $record);

        if ($existant = $record->workflow()->first()) {
            return $existant;
        }

        try {
            return DB::transaction(function () use ($record, $acteur) {
                $workflow = $record->workflow()->create([
                    'cree_par' => $acteur->getKey(),
                    'statut' => WorkflowStatut::Brouillon,
                ]);
                $this->journaliser($workflow, $acteur, 'creation', WorkflowStatut::Brouillon);

                return $workflow;
            });
        } catch (UniqueConstraintViolationException) {
            return $record->workflow()->firstOrFail();
        }
    }

    public function soumettre(Model $record, User $acteur, ?string $commentaire = null): Workflow
    {
        $this->ouvrir($record, $acteur);
        $roles = $record::rhModule()->creationRoles();

        if ($record instanceof Document) {
            $this->renouvellement->verifier($record);
        }

        $this->effets->verifierSoumission($record);

        $workflow = DB::transaction(function () use ($record, $acteur, $commentaire, $roles) {
            $workflow = $this->verrouiller($record);
            $role = $this->exigerRole($acteur, $roles, $record);

            // Reprise d'un dossier rejeté ou expiré : retour au brouillon puis nouvelle soumission.
            if (in_array($workflow->statut, [WorkflowStatut::Rejete, WorkflowStatut::Expire], true)) {
                $this->transitionner($workflow, WorkflowStatut::Brouillon);
                $workflow->fill(['valide_par' => null, 'approuve_par' => null, 'date_validation' => null,
                    'date_approbation' => null, 'date_limite_approbation' => null])->save();
                $this->journaliser($workflow, $acteur, 'reprise', WorkflowStatut::Brouillon, $commentaire, $role);
            }

            $limite = now()->addHours(config('workflow.delai_validation_heures'));

            $this->transitionner($workflow, WorkflowStatut::DocumentSoumis);
            $workflow->date_soumission = now();
            $this->journaliser($workflow, $acteur, 'soumission', WorkflowStatut::DocumentSoumis, $commentaire, $role);

            $this->transitionner($workflow, WorkflowStatut::EnAttenteValidation);
            $workflow->date_limite_validation = $limite;
            $workflow->save();
            $this->journaliser($workflow, $acteur, 'attente_validation', WorkflowStatut::EnAttenteValidation, null, $role, $limite);

            return $workflow;
        });

        $this->notifier->soumis($record);

        return $workflow;
    }

    public function valider(Model $record, User $acteur, ?string $commentaire = null): Workflow
    {
        $workflow = DB::transaction(function () use ($record, $acteur, $commentaire) {
            $workflow = $this->verrouiller($record);
            $this->exigerStatut($workflow, WorkflowStatut::EnAttenteValidation);
            $role = $this->exigerRole($acteur, [Role::RhSenior], $record);
            $this->exigerSeparation($workflow, $acteur);

            $this->transitionner($workflow, WorkflowStatut::Valide);
            $workflow->fill(['valide_par' => $acteur->getKey(), 'date_validation' => now()])->save();
            $this->journaliser($workflow, $acteur, 'validation', WorkflowStatut::Valide, $commentaire, $role);

            $limite = now()->addHours(config('workflow.delai_approbation_heures'));
            $this->transitionner($workflow, WorkflowStatut::EnAttenteApprobation);
            $workflow->date_limite_approbation = $limite;
            $workflow->save();
            $this->journaliser($workflow, $acteur, 'attente_approbation', WorkflowStatut::EnAttenteApprobation, null, $role, $limite);

            return $workflow;
        });

        $this->notifier->valide($record);

        return $workflow;
    }

    /** Rejet à l'étape en cours (Senior RH en validation, Manager RH en approbation). */
    public function rejeter(Model $record, User $acteur, string $commentaire): Workflow
    {
        if (trim($commentaire) === '') {
            throw ValidationException::withMessages(['commentaire' => 'Un commentaire est obligatoire en cas de rejet.']);
        }

        $workflow = DB::transaction(function () use ($record, $acteur, $commentaire) {
            $workflow = $this->verrouiller($record);

            $role = match ($workflow->statut) {
                WorkflowStatut::EnAttenteValidation => Role::RhSenior,
                WorkflowStatut::EnAttenteApprobation => Role::RhManager,
                default => throw new WorkflowException("Le dossier ne peut pas être rejeté au statut {$workflow->statut->value}."),
            };
            $this->exigerRole($acteur, [$role], $record);
            $this->exigerSeparation($workflow, $acteur);

            $this->transitionner($workflow, WorkflowStatut::Rejete);
            $workflow->commentaire = $commentaire;
            $workflow->save();
            $this->journaliser($workflow, $acteur, 'rejet', WorkflowStatut::Rejete, $commentaire, $role);

            return $workflow;
        });

        $this->notifier->rejete($record, $commentaire);

        return $workflow;
    }

    /**
     * Approbation finale : statut, référence officielle et historique dans une
     * seule transaction. PDF, signature et e-mails suivent via DossierApprouve.
     */
    public function approuver(Model $record, User $acteur, ?string $commentaire = null): Workflow
    {
        $workflow = DB::transaction(function () use ($record, $acteur, $commentaire) {
            $workflow = $this->verrouiller($record);
            $this->exigerStatut($workflow, WorkflowStatut::EnAttenteApprobation);
            $role = $this->exigerRole($acteur, [Role::RhManager], $record);
            $this->exigerSeparation($workflow, $acteur);

            if ($record instanceof Document) {
                $this->renouvellement->verifier($record);
                $record->date_production ??= today();
                $record->save();
            }

            $this->transitionner($workflow, WorkflowStatut::Approuve);
            $workflow->fill(['approuve_par' => $acteur->getKey(), 'date_approbation' => now()])->save();
            $this->journaliser($workflow, $acteur, 'approbation', WorkflowStatut::Approuve, $commentaire, $role);

            $reference = $this->references->assign($record);
            $this->journaliser($workflow, $acteur, 'attribution_reference', WorkflowStatut::Approuve, $reference, $role);

            // Promotions/mutations : la situation officielle de l'employé ne change qu'ici,
            // dans la même transaction que l'approbation (ou plus tard si la date d'effet est future).
            $this->effets->verifierSoumission($record);
            $this->effets->appliquerSiEchu($record, $workflow, $acteur);

            return $workflow;
        });

        $this->notifier->approuve($record);

        // L'approbation est acquise : un incident de mise en file ne doit pas l'annuler.
        // Le PDF se relance sans nouvelle référence (`dossiers:finaliser`).
        try {
            DossierApprouve::dispatch($workflow);
        } catch (Throwable $e) {
            report($e);
        }

        return $workflow;
    }

    /** Trace une étape technique ou une modification (PDF, signature, archivage...). */
    public function journaliser(Workflow $workflow, ?User $acteur, string $etape, WorkflowStatut $statut, ?string $commentaire = null, ?Role $role = null, $dateLimite = null): WorkflowHistory
    {
        $entree = $workflow->histories()->create([
            'user_id' => $acteur?->getKey(),
            'role' => $role?->value,
            'etape' => $etape,
            'statut' => $statut->value,
            'commentaire' => $commentaire,
            'date_limite' => $dateLimite,
            'date_action' => now(),
        ]);

        $this->audit->enregistrer($acteur, 'workflow.'.$etape, trim($statut->value.' '.$commentaire), $workflow->workflowable);

        return $entree;
    }

    private function verrouiller(Model $record): Workflow
    {
        $workflow = $record->workflow()->first();

        if ($workflow === null) {
            throw new WorkflowException("Aucun workflow n'est ouvert pour ce dossier.");
        }

        // Relecture sous verrou : deux actions simultanées ne passent pas toutes les deux.
        return Workflow::query()->lockForUpdate()->findOrFail($workflow->getKey());
    }

    private function exigerStatut(Workflow $workflow, WorkflowStatut $attendu): void
    {
        if ($workflow->statut !== $attendu) {
            throw new WorkflowException("Action impossible : le dossier est au statut {$workflow->statut->value} (attendu : {$attendu->value}).");
        }
    }

    private function transitionner(Workflow $workflow, WorkflowStatut $cible): void
    {
        if (! $workflow->statut->peutPasserA($cible)) {
            throw new WorkflowException("Transition interdite : {$workflow->statut->value} → {$cible->value}.");
        }

        $workflow->statut = $cible;
        $workflow->save();
    }

    /**
     * Contrôle rôle, activité et périmètre de l'acteur sur l'employé du dossier.
     *
     * @param  list<Role>  $rolesAutorises
     */
    private function exigerRole(User $acteur, array $rolesAutorises, Model $record): Role
    {
        $role = $acteur->is_active
            ? collect($rolesAutorises)->first(fn (Role $r) => $acteur->hasRole($r))
            : null;

        if ($role === null || ! $record->employe?->isInScopeOf($acteur)) {
            throw new AuthorizationException("Vous n'êtes pas autorisé à effectuer cette action sur ce dossier.");
        }

        return $role;
    }

    private function exigerSeparation(Workflow $workflow, User $acteur): void
    {
        if (! config('workflow.separation_des_taches')) {
            return;
        }

        if ($acteur->getKey() === $workflow->cree_par || $acteur->getKey() === $workflow->valide_par) {
            throw new AuthorizationException('Séparation des tâches : vous ne pouvez pas traiter un dossier que vous avez créé ou validé.');
        }
    }
}
