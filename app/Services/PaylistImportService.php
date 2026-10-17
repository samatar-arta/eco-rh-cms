<?php

namespace App\Services;

use App\Enums\PaylistImportStatut;
use App\Enums\Role;
use App\Exceptions\PaylistException;
use App\Models\Employe;
use App\Models\Paylist;
use App\Models\PaylistImport;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Import de bulletins exportés de Sage : archive ZIP de PDF (ou PDF unique)
 * nommés `<matricule>.pdf` / `<matricule>_<suite>.pdf`. Deux temps :
 * analyse (aperçu, rien d'importé) puis confirmation (lignes valides seules ;
 * les lignes rejetées sont rapportées sans bloquer les autres, et reprenables).
 */
class PaylistImportService
{
    public const OK = 'OK';

    public const MATRICULE_INCONNU = 'MATRICULE_INCONNU';

    public const DOUBLON_ARCHIVE = 'DOUBLON_ARCHIVE';

    public const DOUBLON_EXISTANT = 'DOUBLON_EXISTANT';

    public const FICHIER_INVALIDE = 'FICHIER_INVALIDE';

    public const NOM_INVALIDE = 'NOM_INVALIDE';

    public const TROP_VOLUMINEUX = 'TROP_VOLUMINEUX';

    /** Étape 1 : stocke la référence du fichier téléversé et produit l'aperçu. */
    public function analyser(User $user, string $chemin, string $nomOriginal, int $mois, int $annee): PaylistImport
    {
        $this->autoriser($user);

        if ($mois < 1 || $mois > 12 || $annee < 2000 || $annee > 2100) {
            throw new PaylistException('Période de paie invalide.');
        }

        $import = new PaylistImport([
            'nom_fichier' => Str::limit(basename($nomOriginal), 250, ''),
            'chemin_fichier' => $chemin,
            'mois' => $mois,
            'annee' => $annee,
            'importe_par' => $user->getKey(),
            'statut_import' => PaylistImportStatut::Analyse,
        ]);

        $lignes = $this->evaluer($import);

        $import->fill([
            'total_lignes' => count($lignes),
            'apercu' => $this->resume($lignes),
        ])->save();

        app(AuditService::class)->enregistrer($user, 'paylist.analyse', "{$import->periode()} : ".count($lignes).' ligne(s) analysée(s)', $import);

        return $import;
    }

    /** Étape 2 : importe les lignes valides ; rejouable pour reprendre les rejets. */
    public function confirmer(User $user, PaylistImport $import): PaylistImport
    {
        $this->autoriser($user);

        $verrou = DB::transaction(function () use ($import) {
            $courant = PaylistImport::whereKey($import->getKey())->lockForUpdate()->firstOrFail();

            if (! $courant->statut_import->confirmable()) {
                return null;
            }

            return $courant;
        });

        if ($verrou === null) {
            return $import->refresh();
        }

        try {
            $lignes = $this->evaluer($verrou, true);
        } catch (PaylistException $e) {
            return $this->terminer($verrou, [], PaylistImportStatut::Echec, [$this->erreur('—', self::FICHIER_INVALIDE, $e->getMessage())]);
        }

        $importees = 0;
        $erreurs = [];

        foreach ($lignes as $ligne) {
            if ($ligne['statut'] === self::OK) {
                $erreur = $this->importerLigne($user, $verrou, $ligne);

                if ($erreur === null) {
                    $importees++;

                    continue;
                }

                $ligne['statut'] = $erreur;
            } elseif ($ligne['statut'] === 'DEJA_IMPORTE') {
                $importees++;

                continue;
            }

            $erreurs[] = $this->erreur($ligne['entree'], $ligne['statut'], $this->message($ligne['statut']));
        }

        $statut = match (true) {
            $lignes === [] || ($importees === 0) => PaylistImportStatut::Echec,
            $erreurs !== [] => PaylistImportStatut::ImporteAvecErreurs,
            default => PaylistImportStatut::Importe,
        };

        Log::info('Import Paylist terminé', [
            'import_id' => $verrou->getKey(), 'user_id' => $user->getKey(),
            'importees' => $importees, 'rejetees' => count($erreurs),
        ]);

        $termine = $this->terminer($verrou, $lignes, $statut, $erreurs, $importees);
        app(AuditService::class)->enregistrer($user, 'paylist.import', "{$termine->periode()} : {$importees} importé(s), ".count($erreurs).' rejeté(s)', $termine);
        app(NotificationService::class)->importTermine($termine);

        return $termine;
    }

    /**
     * @return list<array{entree:string,matricule:?string,statut:string,message:string}>
     */
    private function evaluer(PaylistImport $import, bool $avecFlux = false): array
    {
        $entrees = $this->entrees($import);

        $connus = Employe::query()->pluck('matricule')
            ->mapWithKeys(fn ($m) => [mb_strtoupper($m) => $m])->all();

        $existants = Paylist::query()
            ->where('mois', $import->mois)->where('annee', $import->annee)
            ->get(['matricule', 'paylist_import_id'])
            ->mapWithKeys(fn ($p) => [mb_strtoupper($p->matricule) => $p->paylist_import_id])->all();

        $vus = [];
        $lignes = [];
        $total = 0;
        $maxTotal = config('rh.paylist.max_total_mo') * 1024 * 1024;

        foreach ($entrees as $entree) {
            $total += $entree['taille'];
            $ligne = ['entree' => $entree['nom'], 'matricule' => null, 'statut' => self::OK, 'index' => $entree['index']];

            $matricule = $this->matriculeDepuisNom($entree['nom']);
            $ligne['matricule'] = $matricule;

            if ($matricule === null) {
                $ligne['statut'] = self::NOM_INVALIDE;
            } elseif ($entree['taille'] > config('rh.paylist.max_fichier_ko') * 1024 || $total > $maxTotal) {
                $ligne['statut'] = self::TROP_VOLUMINEUX;
            } elseif (! $this->estUnPdf($entree)) {
                $ligne['statut'] = self::FICHIER_INVALIDE;
            } elseif (! isset($connus[mb_strtoupper($matricule)])) {
                $ligne['statut'] = self::MATRICULE_INCONNU;
            } else {
                $cle = mb_strtoupper($matricule);
                $ligne['matricule'] = $connus[$cle];

                if (isset($vus[$cle])) {
                    $ligne['statut'] = self::DOUBLON_ARCHIVE;
                } elseif (isset($existants[$cle])) {
                    $ligne['statut'] = $existants[$cle] === $import->getKey() ? 'DEJA_IMPORTE' : self::DOUBLON_EXISTANT;
                }

                $vus[$cle] = true;
            }

            $ligne['message'] = $this->message($ligne['statut']);
            $lignes[] = $avecFlux ? $ligne + ['source' => $entree['lire']] : $ligne;
        }

        return $lignes;
    }

    /** @return list<array{index:int,nom:string,taille:int,lire:callable}> */
    private function entrees(PaylistImport $import): array
    {
        $disque = Storage::disk(config('rh.paylist.disk'));

        if (! $disque->exists($import->chemin_fichier)) {
            throw new PaylistException('Le fichier téléversé est introuvable.');
        }

        $chemin = $disque->path($import->chemin_fichier);
        $tete = file_get_contents($chemin, false, null, 0, 5);

        if ($tete === '%PDF-') {
            return [[
                'index' => 0,
                'nom' => basename($import->nom_fichier),
                'taille' => (int) filesize($chemin),
                'lire' => fn (int $max) => (string) file_get_contents($chemin, false, null, 0, $max + 1),
            ]];
        }

        $zip = new ZipArchive;

        if (strncmp((string) $tete, 'PK', 2) !== 0 || $zip->open($chemin, ZipArchive::RDONLY) !== true) {
            throw new PaylistException('Le fichier doit être une archive ZIP ou un PDF.');
        }

        if ($zip->numFiles > config('rh.paylist.max_entrees')) {
            $zip->close();
            throw new PaylistException('Archive trop volumineuse : trop de fichiers.');
        }

        $entrees = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $nom = $stat['name'];

            if (str_ends_with($nom, '/') || str_contains($nom, '__MACOSX/') || str_starts_with(basename($nom), '.')) {
                continue;
            }

            // Seul le nom de base est utilisé : le chemin interne n'atteint jamais le disque.
            $entrees[] = [
                'index' => $i,
                'nom' => basename($nom),
                'taille' => (int) $stat['size'],
                'lire' => function (int $max) use ($chemin, $i) {
                    $z = new ZipArchive;
                    $z->open($chemin, ZipArchive::RDONLY);
                    $flux = $z->getStreamIndex($i);
                    $contenu = $flux ? stream_get_contents($flux, $max + 1) : '';
                    $z->close();

                    return (string) $contenu;
                },
            ];
        }

        $zip->close();

        return $entrees;
    }

    private function estUnPdf(array $entree): bool
    {
        return str_starts_with(($entree['lire'])(8), '%PDF-');
    }

    private function matriculeDepuisNom(string $nom): ?string
    {
        if (! preg_match('/^([A-Za-z0-9][A-Za-z0-9.\-]{0,29})(?:[_ ].*)?\.pdf$/i', $nom, $m)) {
            return null;
        }

        return $m[1];
    }

    /** @return string|null code d'erreur, null si importé */
    private function importerLigne(User $user, PaylistImport $import, array $ligne): ?string
    {
        $max = config('rh.paylist.max_fichier_ko') * 1024;
        $contenu = ($ligne['source'])($max);

        if (strlen($contenu) > $max) {
            return self::TROP_VOLUMINEUX;
        }

        if (! str_starts_with($contenu, '%PDF-')) {
            return self::FICHIER_INVALIDE;
        }

        $chemin = sprintf('%s/%d/%02d/%s.pdf', config('rh.paylist.directory'), $import->annee, $import->mois, Str::uuid());
        $disque = Storage::disk(config('rh.paylist.disk'));

        try {
            DB::transaction(function () use ($user, $import, $ligne, $contenu, $chemin, $disque) {
                $disque->put($chemin, $contenu);

                Paylist::create([
                    'paylist_import_id' => $import->getKey(),
                    'matricule' => $ligne['matricule'],
                    'mois' => $import->mois,
                    'annee' => $import->annee,
                    'fichier_path' => $chemin,
                    'empreinte_fichier' => hash('sha256', $contenu),
                    'importe_par' => $user->getKey(),
                ]);
            });
        } catch (QueryException) {
            // Course sur la contrainte d'unicité : le fichier orphelin est retiré.
            $disque->delete($chemin);

            return self::DOUBLON_EXISTANT;
        }

        return null;
    }

    private function terminer(PaylistImport $import, array $lignes, PaylistImportStatut $statut, array $erreurs, int $importees = 0): PaylistImport
    {
        $import->fill([
            'statut_import' => $statut,
            'date_import' => now(),
            'total_lignes' => count($lignes) ?: $import->total_lignes,
            'lignes_importees' => $importees,
            'lignes_rejetees' => count($erreurs),
            'rapport_erreurs' => $erreurs,
        ])->save();

        return $import;
    }

    /** @return list<array{entree:string,matricule:?string,statut:string,message:string}> */
    private function resume(array $lignes): array
    {
        return array_map(fn ($l) => [
            'entree' => $l['entree'], 'matricule' => $l['matricule'],
            'statut' => $l['statut'], 'message' => $l['message'],
        ], $lignes);
    }

    private function erreur(string $entree, string $code, string $message): array
    {
        return ['entree' => $entree, 'statut' => $code, 'message' => $message];
    }

    private function message(string $statut): string
    {
        return match ($statut) {
            self::OK => 'Prêt à importer',
            'DEJA_IMPORTE' => 'Déjà importé dans ce lot',
            self::MATRICULE_INCONNU => 'Matricule inconnu',
            self::DOUBLON_ARCHIVE => 'Matricule présent plusieurs fois dans l\'archive',
            self::DOUBLON_EXISTANT => 'Bulletin déjà importé pour cet employé et cette période',
            self::FICHIER_INVALIDE => 'Fichier PDF invalide',
            self::NOM_INVALIDE => 'Nom de fichier non conforme (attendu : MATRICULE.pdf)',
            self::TROP_VOLUMINEUX => 'Fichier trop volumineux',
            default => $statut,
        };
    }

    private function autoriser(User $user): void
    {
        if (! $user->is_active || ! $user->hasRole(Role::GestionnairePaie)) {
            throw new PaylistException('Seul un gestionnaire de paie actif peut importer des bulletins.');
        }
    }
}
