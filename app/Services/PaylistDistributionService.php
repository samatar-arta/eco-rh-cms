<?php

namespace App\Services;

use App\Enums\DistributionCanal;
use App\Enums\DistributionStatut;
use App\Enums\PaylistStatut;
use App\Enums\Role;
use App\Exceptions\PaylistException;
use App\Jobs\EnvoyerBulletinJob;
use App\Mail\BulletinPaieMail;
use App\Models\Contact;
use App\Models\Paylist;
use App\Models\PaylistDistribution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Distribution des bulletins aux contacts externes. Un destinataire n'est
 * jamais servi sans que son rattachement société/département à l'employé
 * ait été vérifié (à la demande ET au moment de l'envoi).
 */
class PaylistDistributionService
{
    /**
     * @param  iterable<int>  $paylistIds
     * @return list<PaylistDistribution>
     *
     * @throws PaylistException si un seul destinataire/bulletin est refusé (rien n'est créé)
     */
    public function distribuer(User $user, iterable $paylistIds, Contact $contact, DistributionCanal $canal, ?string $preuve = null): array
    {
        $this->autoriser($user);

        $paylists = Paylist::query()->with('employe')->whereIn('id', collect($paylistIds)->all())->get();

        if ($paylists->isEmpty()) {
            throw new PaylistException('Aucun bulletin sélectionné.');
        }

        foreach ($paylists as $paylist) {
            try {
                $this->verifierDestinataire($paylist, $contact, $canal);
            } catch (PaylistException $e) {
                Log::warning('Distribution Paylist refusée', [
                    'paylist_id' => $paylist->getKey(), 'contact_id' => $contact->getKey(),
                    'user_id' => $user->getKey(), 'motif' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        if ($canal === DistributionCanal::Remise && blank($preuve)) {
            throw new PaylistException('Une preuve de remise (référence du bordereau) est obligatoire.');
        }

        $distributions = DB::transaction(function () use ($user, $paylists, $contact, $canal, $preuve) {
            return $paylists->map(function (Paylist $paylist) use ($user, $contact, $canal, $preuve) {
                // Idempotent : la contrainte unique (paylist, contact) évite les doublons.
                $distribution = PaylistDistribution::firstOrCreate(
                    ['paylist_id' => $paylist->getKey(), 'contact_id' => $contact->getKey()],
                    ['distribue_par' => $user->getKey(), 'canal' => $canal, 'statut' => DistributionStatut::EnAttente,
                        'preuve_distribution' => $canal === DistributionCanal::Remise ? $preuve : null],
                );

                return $distribution;
            })->all();
        });

        foreach ($distributions as $distribution) {
            if ($distribution->statut !== DistributionStatut::EnAttente) {
                continue; // déjà envoyé, en cours ou en échec (relance explicite)
            }

            if ($distribution->canal === DistributionCanal::Email) {
                EnvoyerBulletinJob::dispatch($distribution->getKey())->afterCommit();
            } else {
                $this->envoyer($distribution);
            }
        }

        app(AuditService::class)->enregistrer($user, 'paylist.distribution', count($distributions)." bulletin(s) vers le contact #{$contact->getKey()} par {$canal->value}", $contact);

        return array_map(fn ($d) => $d->refresh(), $distributions);
    }

    /** Nouvelle tentative explicite d'une distribution en échec. */
    public function relancer(User $user, PaylistDistribution $distribution): PaylistDistribution
    {
        $this->autoriser($user);

        $reprise = DB::transaction(function () use ($distribution) {
            $courant = PaylistDistribution::whereKey($distribution->getKey())->lockForUpdate()->firstOrFail();

            if ($courant->statut !== DistributionStatut::Echec) {
                return false;
            }

            $courant->update(['statut' => DistributionStatut::EnAttente]);

            return true;
        });

        if ($reprise) {
            $distribution->refresh();
            $distribution->canal === DistributionCanal::Email
                ? EnvoyerBulletinJob::dispatch($distribution->getKey())->afterCommit()
                : $this->envoyer($distribution);
        }

        return $distribution->refresh();
    }

    /** Exécute l'envoi ; sans effet si la distribution n'est plus en attente. */
    public function envoyer(PaylistDistribution $distribution): PaylistDistribution
    {
        $reclame = DB::transaction(function () use ($distribution) {
            $courant = PaylistDistribution::whereKey($distribution->getKey())->lockForUpdate()->firstOrFail();

            if ($courant->statut !== DistributionStatut::EnAttente) {
                return null;
            }

            $courant->update(['statut' => DistributionStatut::EnCours, 'tentatives' => $courant->tentatives + 1]);

            return $courant;
        });

        // Déjà traitée (ou en cours) par un autre worker : rien à renvoyer.
        return $reclame === null ? $distribution->refresh() : $this->executer($reclame);
    }

    private function executer(PaylistDistribution $distribution): PaylistDistribution
    {
        $distribution->load(['paylist.employe', 'contact']);

        try {
            $this->verifierDestinataire($distribution->paylist, $distribution->contact, $distribution->canal);

            if ($distribution->canal === DistributionCanal::Email) {
                Mail::to($distribution->contact->email)->send(new BulletinPaieMail($distribution->paylist, $distribution->contact));
            }
        } catch (Throwable $e) {
            $distribution->update([
                'statut' => DistributionStatut::Echec,
                'commentaire' => Str::limit(class_basename($e).' : '.$e->getMessage(), 500),
            ]);

            Log::error('Échec de distribution Paylist', [
                'distribution_id' => $distribution->getKey(), 'erreur' => class_basename($e),
            ]);
            app(AuditService::class)->enregistrer(null, 'paylist.distribution_echec', class_basename($e), $distribution);
            app(NotificationService::class)->distributionEchec($distribution);

            return $distribution;
        }

        app(AuditService::class)->enregistrer(null, 'paylist.distribution_envoyee', 'Bulletin '.$distribution->paylist->matricule.' '.$distribution->paylist->periode(), $distribution);

        DB::transaction(function () use ($distribution) {
            $distribution->update([
                'statut' => DistributionStatut::Envoye, 'date_distribution' => now(), 'commentaire' => null,
            ]);
            $distribution->paylist->update(['statut' => PaylistStatut::Distribue]);
        });

        return $distribution;
    }

    public function verifierDestinataire(Paylist $paylist, Contact $contact, DistributionCanal $canal): void
    {
        $employe = $paylist->employe ?? throw new PaylistException('Employé du bulletin introuvable.');

        if (! $contact->is_active) {
            throw new PaylistException("Le contact {$contact->nom} est inactif.");
        }

        if ($contact->societe_id !== $employe->societe_id) {
            throw new PaylistException("Le contact {$contact->nom} n'appartient pas à la société de l'employé {$employe->matricule}.");
        }

        if ($contact->departement_id !== null && $contact->departement_id !== $employe->departement_id) {
            throw new PaylistException("Le contact {$contact->nom} n'est pas responsable du département de l'employé {$employe->matricule}.");
        }

        if ($canal === DistributionCanal::Email && ! filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
            throw new PaylistException("Le contact {$contact->nom} n'a pas d'adresse e-mail valide.");
        }

        if (! Storage::disk(config('rh.paylist.disk'))->exists($paylist->fichier_path)) {
            throw new PaylistException("Fichier du bulletin {$employe->matricule} introuvable.");
        }
    }

    private function autoriser(User $user): void
    {
        if (! $user->is_active || ! $user->hasRole(Role::GestionnairePaie)) {
            throw new PaylistException('Seul un gestionnaire de paie actif peut distribuer des bulletins.');
        }
    }
}
