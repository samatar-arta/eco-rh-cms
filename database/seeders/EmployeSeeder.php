<?php

namespace Database\Seeders;

use App\Models\Contrat;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Importe database/data/employes.csv (export Excel, Windows-1252 ou UTF-8, séparateur « , »).
 * Lecture ligne par ligne, insertion par blocs. Idempotent (matricule unique : les
 * matricules déjà présents sont ignorés). Le fichier contient des données personnelles
 * et n'est pas versionné.
 *
 * L'âge et l'ancienneté du CSV ne sont pas repris : ils sont calculés depuis
 * date_naissance et date_embauche.
 *
 * Société et département (NOT NULL, FK composite) sont déduits du contrat et du libellé de
 * département du CSV, en s'appuyant sur les départements DÉJÀ présents en base : le contrat
 * désigne un département « ancre » dont la société est reprise (les codes de société
 * ne sont pas utilisés), puis le libellé est cherché dans cette société.
 */
class EmployeSeeder extends Seeder
{
    private const CHUNK = 200;

    /** Position des colonnes du CSV (l'en-tête contient deux « Categorie »). */
    private const COL = [
        'matricule' => 1, 'name' => 2, 'date_naissance' => 3, 'date_embauche' => 5,
        'fonction' => 7, 'departement' => 8, 'contrat' => 9, 'statut' => 11, 'num_cnss' => 12, 'num_cni' => 13,
        'niveau_etude' => 15, 'adresse' => 16, 'telephone' => 17, 'situation_familiale' => 18,
        'personne_contacter' => 19, 'lien_parental' => 20, 'telephone_parental' => 21,
        'formation_certificat' => 22, 'permis_conduite' => 23, 'categorie' => 24, 'handicap' => 25,
    ];

    /** Famille de contrat (fragment du code) => département ancre, qui donne la société. */
    private const ANCRES = [
        'SGTD' => 'RESSOURCE HUMAINE',
        'DMP' => 'DEPT SUPPORT&INFRASTRUCTURE',
        'DLS' => 'ADMINISTRATION DLS',
        'FREE_ZONE' => 'FME FREE ZONE',
    ];

    /** Libellé CSV (minuscules) => noms de département à essayer, dans l'ordre. */
    private const DEPARTEMENTS = [
        'rh' => ['RESSOURCE HUMAINE'],
        'rh sgtd' => ['RESSOURCE HUMAINE'],
        'operation' => ['OPERATIONS DMP', 'OPERATIONS'],
        'ops' => ['OPERATIONS'],
        'operation dmp' => ['OPERATIONS DMP'],
        'technique' => ['TECHNIQUE'],
        'dsi' => ['DEPT SUPPORT&INFRASTRUCTURE'],
        'dsi nouveau' => ['DEPT SUPPORT&INFRASTRUCTURE'],
        'qhse' => ['QUALITE,HYGIENE,SECURITE&ENVIRONNEMENT'],
        'precheck list' => ['PRECHECK LIST'],
        'ops dahez' => ['OPS DAHEZ'],
        'administration dls' => ['ADMINISTRATION DLS'],
        'fme free zone' => ['FME FREE ZONE'],
    ];

    /** @var array<string, int> */
    private array $contrats = [];

    /** @var array<string, array{societe_id: int, departements: array<string, int>, ancre: int}> */
    private array $familles = [];

    public function run(): void
    {
        $fichier = database_path('data/employes.csv');
        if (! is_file($fichier)) {
            throw new RuntimeException("Fichier introuvable : {$fichier}");
        }

        $existants = array_flip(DB::table('employes')->pluck('matricule')->all());
        $handle = fopen($fichier, 'r');
        fgetcsv($handle); // en-tête

        $lus = $inseres = $ignores = 0;
        $bloc = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }
            $lus++;
            $employe = $this->employe($row);

            if ($employe === null || isset($existants[$employe['matricule']])) {
                $ignores++;

                continue;
            }
            $existants[$employe['matricule']] = true;
            $bloc[] = $employe;

            if (count($bloc) === self::CHUNK) {
                $inseres += $this->inserer($bloc);
                $bloc = [];
            }
        }
        fclose($handle);

        $inseres += $this->inserer($bloc);

        $this->command?->info("Lignes lues dans employes.csv : {$lus}");
        $this->command?->info("Lignes insérées : {$inseres} (ignorées : {$ignores})");
    }

    /** @param list<?string> $row */
    private function employe(array $row): ?array
    {
        $v = fn (string $col): ?string => $this->texte($row[self::COL[$col]] ?? null);

        $matricule = $v('matricule');
        if ($matricule === null) {
            return null;
        }

        // « Inactif » du CSV = sorti ; les statuts connus de l'enum sont conservés.
        $statut = match (Str::upper($v('statut') ?? 'ACTIF')) {
            'INACTIF' => 'SORTI',
            'SUSPENDU' => 'SUSPENDU',
            default => 'ACTIF',
        };
        $now = now();

        return [
            'matricule' => $matricule,
            'name' => $v('name') ?? $matricule,
            'fonction' => $v('fonction'),
            'date_naissance' => $this->date($v('date_naissance')),
            'date_embauche' => $this->date($v('date_embauche')),
            'contrat_id' => $this->contratId($v('contrat')),
            ...$this->affectation($v('contrat'), $v('departement')),
            'statut_employe' => $statut,
            'telephone' => $v('telephone'),
            'num_cnss' => $v('num_cnss'),
            'num_cni' => $v('num_cni'),
            'niveau_etude' => $v('niveau_etude'),
            'adresse' => $v('adresse'),
            'situation_familiale' => $v('situation_familiale'),
            'personne_contacter' => $v('personne_contacter'),
            'lien_parental' => $v('lien_parental'),
            'telephone_parental' => $v('telephone_parental'),
            'formation_certificat' => $v('formation_certificat'),
            'permis_conduite' => $v('permis_conduite'),
            'categorie' => $v('categorie'),
            'handicap' => in_array(Str::lower($v('handicap') ?? ''), ['oui', 'ouii', '1', 'true'], true),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** @param list<array<string, mixed>> $bloc */
    private function inserer(array $bloc): int
    {
        if ($bloc === []) {
            return 0;
        }
        DB::table('employes')->insert($bloc);

        return count($bloc);
    }

    /** Convertit en UTF-8 (Windows-1252 si besoin), retire BOM et espaces ; vide => null. */
    private function texte(?string $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        if (! mb_check_encoding($valeur, 'UTF-8')) {
            $valeur = mb_convert_encoding($valeur, 'UTF-8', 'Windows-1252');
        }
        $valeur = trim(str_replace("\u{FEFF}", '', $valeur));

        return $valeur === '' ? null : $valeur;
    }

    /** d/m/Y => Y-m-d ; date invalide => null. */
    private function date(?string $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $date = Carbon::createFromFormat('!d/m/Y', $valeur);
        $erreurs = Carbon::getLastErrors();

        return $date && empty($erreurs['warning_count']) && empty($erreurs['error_count'])
            ? $date->format('Y-m-d')
            : null;
    }

    /** @return array{societe_id: int, departement_id: int} */
    private function affectation(?string $contrat, ?string $departement): array
    {
        $code = Str::upper(Str::slug((string) $contrat, '_'));
        $famille = collect(array_keys(self::ANCRES))->first(fn (string $f) => str_contains($code, $f))
            ?? throw new RuntimeException("Contrat sans société connue : '{$contrat}'");

        $info = $this->familles[$famille] ??= $this->charger($famille);

        foreach (self::DEPARTEMENTS[Str::lower($departement ?? '')] ?? [] as $nom) {
            if (isset($info['departements'][$nom])) {
                return ['societe_id' => $info['societe_id'], 'departement_id' => $info['departements'][$nom]];
            }
        }

        return ['societe_id' => $info['societe_id'], 'departement_id' => $info['ancre']];
    }

    /** @return array{societe_id: int, departements: array<string, int>, ancre: int} */
    private function charger(string $famille): array
    {
        $ancre = DB::table('departements')->where('name', self::ANCRES[$famille])->first()
            ?? throw new RuntimeException('Département « '.self::ANCRES[$famille]." » introuvable : importez d'abord les départements.");

        return [
            'societe_id' => $ancre->societe_id,
            'ancre' => $ancre->id,
            'departements' => DB::table('departements')->where('societe_id', $ancre->societe_id)->pluck('id', 'name')->all(),
        ];
    }

    /** Contrats du référentiel (casse et pluriel normalisés) ; création si inconnu. */
    private function contratId(?string $nom): ?int
    {
        if ($nom === null) {
            return null;
        }

        $code = str_replace('JOURNALIERS', 'JOURNALIER', Str::upper(Str::slug($nom, '_')));

        return $this->contrats[$code] ??= Contrat::firstOrCreate(['code' => $code], ['name' => Str::upper($nom)])->id;
    }
}
