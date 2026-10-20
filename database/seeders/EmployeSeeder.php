<?php

namespace Database\Seeders;

use App\Models\Contrat;
use App\Models\Employe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Importe employes.sql (export phpMyAdmin) : seules les colonnes présentes dans la
 * table employes sont reprises, les autres sont ignorées. Idempotent (matricule unique).
 */
class EmployeSeeder extends Seeder
{
    /** Colonnes du dump dont le nom diffère de la table actuelle. */
    private const ALIAS = [
        'situation_familial' => 'situation_familiale',
    ];

    /** Colonnes du dump jamais reprises (clé technique, identifiants propres à l'ancienne base). */
    private const IGNOREES = ['id', 'cree_par', 'societe_id', 'departement_id'];

    public function run(): void
    {
        $fichier = base_path('employes.sql');
        if (! is_file($fichier)) {
            throw new RuntimeException("Fichier introuvable : {$fichier}");
        }

        $colonnesTable = Schema::getColumnListing('employes');
        $lignes = $this->lireLignes(file_get_contents($fichier));

        $inseres = 0;
        $doublons = 0;
        $erreurs = 0;

        foreach ($lignes as $ligne) {
            $matricule = trim((string) ($ligne['matricule'] ?? ''));
            if ($matricule === '' || Employe::where('matricule', $matricule)->exists()) {
                $doublons++;

                continue;
            }

            $data = [];
            foreach ($ligne as $colonne => $valeur) {
                $cible = self::ALIAS[strtolower($colonne)] ?? strtolower($colonne);
                if (in_array($cible, self::IGNOREES, true) || ! in_array($cible, $colonnesTable, true)) {
                    continue;
                }
                $data[$cible] = $valeur === '' ? null : $valeur;
            }

            $data['matricule'] = $matricule;
            $data['handicap'] = in_array(Str::lower((string) ($data['handicap'] ?? '')), ['oui', '1', 'true'], true);
            $data['statut_employe'] = $data['statut_employe'] ?? 'ACTIF';
            $data['name'] = trim((string) ($data['name'] ?? '')) ?: $matricule;
            $data['contrat_id'] = $this->contratId($ligne['contrat'] ?? null);
            // Société et département : conservés tels quels si la paire existe (FK composite).
            $data['societe_id'] = $ligne['societe_id'] ?? null;
            $data['departement_id'] = $ligne['departement_id'] ?? null;

            try {
                Employe::create($data);
                $inseres++;
            } catch (\Throwable $e) {
                $erreurs++;
                $this->command?->warn("Matricule {$matricule} ignoré : ".$e->getMessage());
            }
        }

        $this->command?->info('Lignes lues dans employes.sql : '.count($lignes));
        $this->command?->info("Lignes insérées : {$inseres} (doublons ignorés : {$doublons}, erreurs : {$erreurs})");
    }

    private function contratId(?string $nom): ?int
    {
        if ($nom === null || trim($nom) === '') {
            return null;
        }

        $nom = trim($nom);

        return Contrat::firstOrCreate(
            ['code' => Str::upper(Str::slug($nom, '_'))],
            ['name' => $nom]
        )->id;
    }

    /** @return list<array<string, ?string>> */
    private function lireLignes(string $sql): array
    {
        $lignes = [];
        $offset = 0;

        while (preg_match('/INSERT INTO `employes` \((.*?)\) VALUES\s*/s', $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $colonnes = array_map(fn ($c) => trim($c, " `\n"), explode(',', $m[1][0]));
            $pos = $m[0][1] + strlen($m[0][0]);
            $len = strlen($sql);

            while ($pos < $len) {
                if ($sql[$pos] !== '(') {
                    break;
                }
                [$valeurs, $pos] = $this->lireTuple($sql, $pos + 1);
                $lignes[] = array_combine($colonnes, $valeurs);

                while ($pos < $len && ctype_space($sql[$pos])) {
                    $pos++;
                }
                if (($sql[$pos] ?? '') === ',') {
                    $pos++;
                    while ($pos < $len && ctype_space($sql[$pos])) {
                        $pos++;
                    }

                    continue;
                }
                break; // ';'
            }
            $offset = $pos;
        }

        return $lignes;
    }

    /** @return array{0: list<?string>, 1: int} valeurs et position après ')' */
    private function lireTuple(string $sql, int $pos): array
    {
        $valeurs = [];
        $len = strlen($sql);

        while ($pos < $len) {
            while (ctype_space($sql[$pos])) {
                $pos++;
            }

            if ($sql[$pos] === "'") {
                $pos++;
                $v = '';
                while ($pos < $len) {
                    $c = $sql[$pos];
                    if ($c === '\\') {
                        $n = $sql[$pos + 1] ?? '';
                        $v .= match ($n) {
                            'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0",
                            default => $n,
                        };
                        $pos += 2;
                    } elseif ($c === "'" && ($sql[$pos + 1] ?? '') === "'") {
                        $v .= "'";
                        $pos += 2;
                    } elseif ($c === "'") {
                        $pos++;
                        break;
                    } else {
                        $v .= $c;
                        $pos++;
                    }
                }
                $valeurs[] = $v;
            } else {
                $debut = $pos;
                while ($pos < $len && $sql[$pos] !== ',' && $sql[$pos] !== ')') {
                    $pos++;
                }
                $brut = trim(substr($sql, $debut, $pos - $debut));
                $valeurs[] = strcasecmp($brut, 'NULL') === 0 ? null : $brut;
            }

            while (ctype_space($sql[$pos])) {
                $pos++;
            }
            if ($sql[$pos] === ',') {
                $pos++;

                continue;
            }
            if ($sql[$pos] === ')') {
                return [$valeurs, $pos + 1];
            }
        }

        throw new RuntimeException('Tuple SQL mal formé dans employes.sql');
    }
}
