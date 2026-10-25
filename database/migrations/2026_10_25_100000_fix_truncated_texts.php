<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace les passages tronqués ([…]) du certificat administratif et du titre de
 * congé par les textes complets validés, redéfinit l'attestation de salaire en
 * modèle simple (montant saisi) et fixe le modèle de sanction par défaut.
 *
 * Même règle que les migrations précédentes : un modèle n'est mis à jour que si sa
 * version est inférieure à la version cible (une édition faite depuis l'interface
 * n'est jamais écrasée). Les dossiers déjà finalisés gardent leur contenu figé.
 */
return new class extends Migration
{
    private const REF = '<p style="text-align:left">REF : {{REFERENCE}} <span style="float:right">Djibouti, le {{DATE_PRODUCTION}}</span></p>';

    private const SIGNATURE = '<p style="text-align:right">Directrice de l\'Administration et des Ressources Humaines<br><br><strong>FATOUMA DAHIR OUMAR</strong><br><br>[[SIGNATURE_DARH]]</p>';

    public function up(): void
    {
        // Données d'exploitation uniquement : les tests partent des seeders.
        if (app()->environment('testing')) {
            return;
        }

        DB::transaction(function () {
            $this->mettreAJour('model_documents', 'type_document_id', 'types_documents', ['CA'], 'Certificat administratif', 2, $this->certificatAdministratif());
            $this->mettreAJour('model_documents', 'type_document_id', 'types_documents', ['AS'], 'Attestation de salaire', 10, $this->attestationSalaire());
            $this->mettreAJour('model_conges', 'type_conge_id', 'types_conges', ['CONGE_ANNUEL', 'CONGE_MATERNITE'], 'Titre de congé', 2, $this->titreConge());
            $this->modeleSanctionParDefaut();
        });
    }

    /** Irréversible : l'ancien contenu n'est pas conservé (les PDF figés ne sont pas touchés). */
    public function down(): void {}

    /** @param list<string> $codes */
    private function mettreAJour(string $table, string $fk, string $tableTypes, array $codes, string $titre, int $version, string $contenu): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($tableTypes)) {
            return;
        }

        DB::table($table)
            ->whereIn($fk, DB::table($tableTypes)->whereIn('code', $codes)->select('id'))
            ->where('titre', $titre)
            ->where('version', '<', $version)
            ->update(['contenu' => $contenu, 'version' => $version, 'updated_at' => now()]);
    }

    /** « Décision sanctionnant un agent » par défaut, « Décision de sanction » non. */
    private function modeleSanctionParDefaut(): void
    {
        if (! Schema::hasTable('model_sanctions')) {
            return;
        }

        $types = DB::table('model_sanctions')->where('titre', 'Décision sanctionnant un agent')->pluck('type_sanction_id');

        foreach ($types as $typeId) {
            DB::table('model_sanctions')->where('type_sanction_id', $typeId)->where('titre', 'Décision de sanction')->update(['is_default' => false, 'updated_at' => now()]);
            DB::table('model_sanctions')->where('type_sanction_id', $typeId)->where('titre', 'Décision sanctionnant un agent')->update(['is_default' => true, 'updated_at' => now()]);
        }
    }

    private function page(string $titre, string $corps, bool $avecRef = true, string $avant = ''): string
    {
        return '<div style="font-family: Arial; padding: 40px;">'."\n"
            .($avecRef ? self::REF."\n<br><br>\n" : '')
            .$avant
            .'<h2 style="text-align:center; text-decoration:underline">'.$titre.'</h2>'."\n<br><br>\n"
            .$corps."\n<br><br><br>\n".self::SIGNATURE."\n</div>";
    }

    private function certificatAdministratif(): string
    {
        return $this->page('CERTIFICAT ADMINISTRATIF',
            "<p>La Direction des Ressources Humaines DLS, certifie que <strong>{{NOM_COMPLET}}</strong>, Fichier <strong>{{MATRICULE}}</strong> de fonction <strong>{{FONCTION}}</strong>, réunis <strong>{{ANCIENNETE}}</strong> ans et <strong>{{ANCIENNETE_MOIS}}</strong> mois de service, à la date du <strong>{{DATE_DERNIER_BULLETIN_DE_PAIE}}</strong>.</p>\n"
            ."<p>En cas d’une éventuelle rupture de contrat de travail prononcée par l’employeur, il lui sera accordé une indemnité de préavis et de licenciement calculé respectivement et conformément aux prescriptions des articles 10 et 20 de la Convention Collective du 26/12/2011.</p>\n"
            ."<p><strong>Indemnité de préavis</strong> de la catégorie <strong>{{CATEGORIE}}</strong> (1 mois de salaire brut).<br>{{SALAIRE_BRUT}} FD x 1 mois = <strong>{{MONTANT_SAISIE_MANUELLE_1}}</strong> FD</p>\n"
            ."<p><strong>Indemnité de licenciement</strong> pour {{ANCIENNETE}} ans<br>{{SALAIRE_BRUT}} FD x {{ANCIENNETE}} x 15/30 = <strong>{{MONTANT_SAISIE_MANUELLE_2}}</strong> FD</p>\n"
            ."<p>Retenue Retraite = <strong>{{MONTANT_SAISIE_MANUELLE_3}}</strong> FD</p>\n"
            ."<p>Net à payer = <strong>{{MONTANT_SAISIE_MANUELLE_4}}</strong> FD</p>\n"
            .'<p>Le droit au bénéfice d’indemnités se perd en cas de licenciement pour faute lourde.<br>Etablie pour servir et valoir ce que de droit.</p>');
    }

    private function attestationSalaire(): string
    {
        return $this->page('ATTESTATION DE SALAIRE',
            "<p>La Direction des Ressources Humaines DLS, certifie que <strong>{{NOM_COMPLET}}</strong>, Fichier <strong>{{MATRICULE}}</strong>, perçoit un salaire mensuel brut de <strong>{{MONTANT_SALAIRE}}</strong> FD.</p>\n"
            .'<p>La présente attestation est délivrée à l\'intéressé pour servir et valoir ce que de droit.</p>');
    }

    private function titreConge(): string
    {
        $identite = '<p>Nom : <strong>{{NOM_COMPLET}}</strong><br>Fichier : <strong>{{MATRICULE}}</strong><br>Fonction : <strong>{{FONCTION}}</strong><br>Département : <strong>{{DEPARTEMENT.NAME}}</strong></p>';

        return $this->page('TITRE DE CONGE',
            $identite."\n"
            ."<p>Est autorisé à s'absenter de Djibouti Labour Services FZCo du <strong>{{DATE_DEBUT}}</strong> au <strong>{{DATE_FIN}}</strong></p>\n"
            ."<p>Pour raison : {{MOTIF}}<br>Date réelle retour : <strong>{{DATE_RETOUR}}</strong></p>\n"
            .'<table style="width:100%; border-collapse:collapse; text-align:center" border="1" cellpadding="6"><tr><th>Nb jours déjà pris</th><th>Nb jours demandés</th><th>Nb jours restant</th></tr><tr><td>{{JOURS_PRIS}}</td><td>{{NBRE_JOURS}}</td><td>{{JOURS_RESTANT}}</td></tr></table>'."\n"
            .'<p style="font-size:10px">Code du Travail : Article 103 : Le congé doit être effectivement pris dans les douze mois suivant la période de référence</p>',
            false,
            '<p style="text-align:left">Planning congé prévu le : <strong>{{ANNEE_CONGE}}</strong></p>'."\n<br>\n");
    }
};
