<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Département propre à DMP : s'il est rattaché à la société codée SGTD, les deux sociétés sont inversées. */
    private const DEPARTEMENT_DMP = 'DEPT SUPPORT&INFRASTRUCTURE';

    /**
     * Corrige les bases importées où les sociétés SGTD et DMP portaient le code et le nom
     * de l'autre (leurs départements et employés étaient rattachés à l'inverse). Échange le
     * code, le nom et la description ; les identifiants ne changent pas, donc aucune clé
     * étrangère n'est touchée. Sans effet si la base est déjà correcte (idempotent).
     */
    public function up(): void
    {
        $societes = DB::table('societes')->whereIn('code', ['SGTD', 'DMP'])->get()->keyBy('code');
        if ($societes->count() !== 2) {
            return;
        }

        $sgtd = $societes['SGTD'];
        $dmp = $societes['DMP'];

        $inversees = DB::table('departements')
            ->where('societe_id', $sgtd->id)
            ->where('name', self::DEPARTEMENT_DMP)
            ->exists();
        if (! $inversees) {
            return;
        }

        DB::transaction(function () use ($sgtd, $dmp) {
            // Valeurs temporaires : code et nom sont uniques.
            DB::table('societes')->where('id', $sgtd->id)->update(['code' => 'TMP_SWAP', 'name' => 'TMP_SWAP']);
            DB::table('societes')->where('id', $dmp->id)->update([
                'code' => $sgtd->code, 'name' => $sgtd->name, 'description' => $sgtd->description,
            ]);
            DB::table('societes')->where('id', $sgtd->id)->update([
                'code' => $dmp->code, 'name' => $dmp->name, 'description' => $dmp->description,
            ]);
        });
    }

    /** Correction de données : on ne rétablit pas volontairement l'inversion. */
    public function down(): void {}
};
