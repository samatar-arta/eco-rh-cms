<?php

namespace App\Services;

use App\Enums\RhModule;
use App\Enums\Role;
use App\Filament\Resources\Conges\CongeResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Filament\Resources\Mutations\MutationResource;
use App\Filament\Resources\PaylistDistributions\PaylistDistributionResource;
use App\Filament\Resources\PaylistImports\PaylistImportResource;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Resources\Sanctions\SanctionResource;
use App\Models\PaylistDistribution;
use App\Models\PaylistImport;
use App\Models\User;
use App\Models\Workflow;
use App\Notifications\AppNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Notifications internes. Les destinataires sont toujours filtrés par rôle,
 * état actif et périmètre de l'employé concerné ; une panne de notification
 * ne fait jamais échouer l'action métier.
 */
class NotificationService
{
    public function soumis(Model $record): void
    {
        $this->versRole($record, Role::RhSenior, 'a_valider', 'Dossier à valider', 'soumis par');
    }

    public function valide(Model $record): void
    {
        $this->versRole($record, Role::RhManager, 'a_approuver', 'Dossier à approuver', 'validé par');
    }

    public function rejete(Model $record, string $commentaire): void
    {
        $this->proteger(function () use ($record, $commentaire) {
            $workflow = $record->workflow()->first();
            $this->envoyer(
                $this->utilisateurs([$workflow->cree_par, $workflow->valide_par]),
                new AppNotification('rejete', 'Dossier rejeté', $this->libelle($record).' a été rejeté : '.$commentaire, $this->url($record)),
            );
        });
    }

    public function approuve(Model $record): void
    {
        $this->proteger(function () use ($record) {
            $workflow = $record->workflow()->first();
            $this->envoyer(
                $this->utilisateurs([$workflow->cree_par, $workflow->valide_par]),
                new AppNotification('approuve', 'Dossier approuvé', $this->libelle($record).' a été approuvé'.($record->reference ? " ({$record->reference})" : '').'.', $this->url($record)),
            );
        });
    }

    /** Relance une seule fois par échéance dépassée. Retourne vrai si une relance est partie. */
    public function retard(Workflow $workflow): bool
    {
        $enValidation = $workflow->statut->value === 'EN_ATTENTE_VALIDATION';
        $limite = $enValidation ? $workflow->date_limite_validation : $workflow->date_limite_approbation;

        if ($limite === null || ($workflow->retard_notifie_le !== null && $workflow->retard_notifie_le->gte($limite))) {
            return false;
        }

        $record = $workflow->workflowable;

        if ($record === null) {
            return false;
        }

        $this->proteger(function () use ($record, $enValidation) {
            $role = $enValidation ? Role::RhSenior : Role::RhManager;
            $this->envoyer(
                $this->destinatairesRole($record, $role),
                new AppNotification('retard', 'Délai de traitement dépassé', $this->libelle($record).' attend votre '.($enValidation ? 'validation' : 'approbation').' au-delà du délai.', $this->url($record)),
            );
        });

        $workflow->forceFill(['retard_notifie_le' => now()])->save();

        return true;
    }

    public function importTermine(PaylistImport $import): void
    {
        $this->proteger(function () use ($import) {
            $erreur = $import->lignes_rejetees > 0;
            $this->envoyer(
                $this->utilisateurs([$import->importe_par]),
                new AppNotification(
                    $erreur ? 'import_erreur' : 'import_termine',
                    $erreur ? 'Import de paie terminé avec erreurs' : 'Import de paie terminé',
                    "{$import->nom_fichier} ({$import->periode()}) : {$import->lignes_importees} importé(s), {$import->lignes_rejetees} rejeté(s).",
                    PaylistImportResource::getUrl('view', ['record' => $import], panel: 'rh'),
                ),
            );
        });
    }

    public function distributionEchec(PaylistDistribution $distribution): void
    {
        $this->proteger(function () use ($distribution) {
            $this->envoyer(
                $this->utilisateurs([$distribution->distribue_par]),
                new AppNotification('distribution_echec', 'Échec de distribution de bulletin', "Bulletin {$distribution->paylist->matricule} ({$distribution->paylist->periode()}) non distribué à {$distribution->contact->nom}.", PaylistDistributionResource::getUrl('index', panel: 'rh')),
            );
        });
    }

    private function versRole(Model $record, Role $role, string $kind, string $titre, string $verbe): void
    {
        $this->proteger(function () use ($record, $role, $kind, $titre, $verbe) {
            $auteur = $record->workflow()->first()?->createur?->name;
            $this->envoyer(
                $this->destinatairesRole($record, $role),
                new AppNotification($kind, $titre, $this->libelle($record).($auteur ? " {$verbe} {$auteur}." : '.'), $this->url($record)),
            );
        });
    }

    /** @return Collection<int, User> */
    private function destinatairesRole(Model $record, Role $role): Collection
    {
        $workflow = $record->workflow()->first();
        $exclus = array_filter([$workflow?->cree_par, $role === Role::RhManager ? $workflow?->valide_par : null]);

        return User::query()
            ->where('is_active', true)
            ->whereHas('roleAssignments', fn ($q) => $q->where('role', $role->value))
            ->whereNotIn('id', $exclus)
            ->get()
            ->filter(fn (User $u) => $record->employe?->isInScopeOf($u));
    }

    /** @param  array<int|null>  $ids @return Collection<int, User> */
    private function utilisateurs(array $ids): Collection
    {
        return User::query()->where('is_active', true)->whereIn('id', array_filter(array_unique($ids)))->get();
    }

    private function envoyer(Collection $users, AppNotification $notification): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
    }

    private function libelle(Model $record): string
    {
        $module = $record::rhModule();

        return ucfirst(strtolower($module->value))." de {$record->employe?->name} ({$record->matricule})";
    }

    private function url(Model $record): ?string
    {
        $resource = match ($record::rhModule()) {
            RhModule::Document => DocumentResource::class,
            RhModule::Conge => CongeResource::class,
            RhModule::Sanction => SanctionResource::class,
            RhModule::Evaluation => EvaluationResource::class,
            RhModule::Promotion => PromotionResource::class,
            RhModule::Mutation => MutationResource::class,
        };

        return $resource::getUrl('view', ['record' => $record], panel: 'rh');
    }

    private function proteger(callable $action): void
    {
        try {
            $action();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
