<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles de documents DLS au design final : espacement REF / titre / corps,
 * bloc de signature DARH ([[SIGNATURE_DARH]], remplacé au rendu du PDF par
 * storage/app/signatures/darh.png). L'en-tête bleu et le pied de page
 * « Approuvé par » ne sont PAS dans le contenu : ils viennent de la mise en page
 * resources/views/pdf/dossier.blade.php, commune à tous les PDF.
 *
 * Idempotent : un modèle n'est mis à jour que si sa version est inférieure à la
 * version cible (une édition ultérieure depuis l'interface n'est jamais écrasée).
 * Les documents déjà finalisés gardent leur contenu figé.
 */
return new class extends Migration
{
    private const SIGNATURE = '<p style="text-align:right">Directrice de l\'Administration et des Ressources Humaines<br><br><strong>FATOUMA DAHIR OUMAR</strong><br><br>[[SIGNATURE_DARH]]</p>';

    private const REF = '<p style="text-align:left">REF : {{REFERENCE}} <span style="float:right">Djibouti, le {{DATE_PRODUCTION}}</span></p>';

    private const CLOTURE = '<p>La présente attestation est délivrée à l\'intéressé pour servir et valoir ce que de droit.</p>';

    public function up(): void
    {
        if (! Schema::hasTable('model_documents') || ! Schema::hasTable('types_documents')) {
            return;
        }

        foreach ($this->modeles() as $code => [$version, $contenu]) {
            $typeId = DB::table('types_documents')->where('code', $code)->value('id');

            if ($typeId === null) {
                continue;
            }

            DB::table('model_documents')
                ->where('type_document_id', $typeId)
                ->where('version', '<', $version)
                ->update(['contenu' => $contenu, 'version' => $version, 'updated_at' => now()]);
        }
    }

    /** Irréversible : l'ancien contenu n'est pas conservé (les PDF figés ne sont pas touchés). */
    public function down(): void {}

    /** @return array<string, array{int, string}> code du type => [version cible, contenu] */
    private function modeles(): array
    {
        $debut = '<div style="font-family: Arial; padding: 40px;">'."\n".self::REF."\n<br><br>\n";
        $fin = "\n".self::CLOTURE."\n<br><br><br>\n".self::SIGNATURE."\n</div>";

        return [
            'AT' => [8, $debut
                .'<h2 style="text-align:center; text-decoration:underline">ATTESTATION DE TRAVAIL</h2>'."\n<br><br>\n"
                .'<p>Je soussignée, Directrice de l\'Administration et des Ressources Humaines, atteste que <strong>{{NOM_COMPLET}}</strong>, fichier <strong>{{MATRICULE}}</strong> est employé à la société Djibouti Labour Services (DLS) depuis le <strong>{{DATE_EMBAUCHE}}</strong> sous contrat à durée indéterminée.</p>'."\n"
                .'<p>L\'intéressé occupe actuellement la fonction de <strong>{{FONCTION}}</strong> au sein de la société.</p>'
                .$fin],
            'AMT' => [6, $debut
                .'<h2 style="text-align:center; text-decoration:underline">ATTESTATION DE MISSION DE TRAVAIL</h2>'."\n<br><br>\n"
                .'<p>Je soussignée, Directrice de l\'Administration et des Ressources Humaines, atteste par la présente que :</p>'."\n"
                .'<p><strong>{{NOM_COMPLET}}</strong>, Fichier : <strong>{{MATRICULE}}</strong> a été mis à la disposition de la société <strong>{{CODE}}</strong> à compter de l\'an <strong>{{ANNEE_EMBAUCHE}}</strong> en qualité d\'agent journalier pour le poste de <strong>{{FONCTION}}</strong>.</p>'
                .$fin],
        ];
    }
};
