<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles issus des documents Word DLS : certificat de travail, certificat
 * administratif, titre de congé, demandes d'autorisation d'absence (jours et
 * heures), décision de sanction. Structure commune : [REF] + titre + corps +
 * [[SIGNATURE_DARH]] ; l'en-tête, le pied de page bleus et la signature (120 px)
 * viennent de resources/views/pdf/dossier.blade.php et de DocumentGenerationService.
 *
 * Les valeurs saisies à la main ({{SALAIRE_BRUT}}, {{DATE_RETOUR}}, etc.) sont
 * déclarées dans App\Services\ChampsSaisieService : elles deviennent des champs
 * obligatoires du formulaire dès que le modèle les utilise.
 *
 * Les modèles n'ont pas de colonne `code` : un modèle est identifié par (type, titre).
 * Idempotent : inséré s'il est absent, mis à jour seulement si sa version est
 * inférieure à la version cible (une édition faite depuis l'interface reste intacte).
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
            foreach ($this->modules() as $module) {
                if (! Schema::hasTable($module['table_types']) || ! Schema::hasTable($module['table_models'])) {
                    continue;
                }

                $ids = [];
                foreach ($module['types'] as [$code, $name, $extra]) {
                    $ids[$code] = $this->type($module['table_types'], $code, $name, $extra);
                }

                foreach ($module['models'] as [$titre, $version, $contenu, $codes]) {
                    foreach ($codes as $code) {
                        $this->modele($module['table_models'], $module['fk'], $ids[$code], $titre, $version, $contenu);
                    }
                }
            }

            $this->placeholderSalaire();
        });
    }

    /** Irréversible : les types/modèles créés peuvent être référencés par des dossiers. */
    public function down(): void {}

    /** L'attestation de salaire utilise désormais le champ saisi {{MONTANT_SALAIRE}}. */
    private function placeholderSalaire(): void
    {
        if (! Schema::hasTable('model_documents')) {
            return;
        }

        DB::table('model_documents')
            ->where('contenu', 'like', '%[MONTANT_SALAIRE]%')
            ->where('version', '<', 9)
            ->get(['id', 'contenu'])
            ->each(fn ($m) => DB::table('model_documents')->where('id', $m->id)->update([
                'contenu' => str_replace('[MONTANT_SALAIRE]', '{{MONTANT_SALAIRE}}', $m->contenu),
                'version' => 9, 'updated_at' => now(),
            ]));
    }

    private function type(string $table, string $code, string $name, array $extra): int
    {
        $id = DB::table($table)->where('code', $code)->value('id');

        return $id ?? DB::table($table)->insertGetId(
            ['code' => $code, 'name' => $name, 'created_at' => now(), 'updated_at' => now()] + $extra
        );
    }

    private function modele(string $table, string $fk, int $typeId, string $titre, int $version, string $contenu): void
    {
        $existant = DB::table($table)->where($fk, $typeId)->where('titre', $titre)->first(['id', 'version']);

        if ($existant === null) {
            DB::table($table)->insert([
                $fk => $typeId, 'titre' => $titre, 'contenu' => $contenu, 'version' => $version,
                'is_default' => ! DB::table($table)->where($fk, $typeId)->where('is_default', true)->exists(),
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return;
        }

        if ($existant->version < $version) {
            DB::table($table)->where('id', $existant->id)
                ->update(['contenu' => $contenu, 'version' => $version, 'updated_at' => now()]);
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

    /** @return list<array<string, mixed>> */
    private function modules(): array
    {
        $identite = '<p>Nom : <strong>{{NOM_COMPLET}}</strong><br>Fichier : <strong>{{MATRICULE}}</strong><br>Fonction : <strong>{{FONCTION}}</strong><br>Département : <strong>{{DEPARTEMENT.NAME}}</strong></p>';

        $certificatTravail = $this->page('CERTIFICAT DE TRAVAIL',
            "<p>Je soussignée, Mme Fatouma Dahir Oumar agissant en qualité de HR&amp;Administration Manager, certifie que, <strong>{{NOM_COMPLET}}</strong>, Fichier <strong>{{MATRICULE}}</strong>, immatriculé à la CNSS n° <strong>{{NUM_CNSS}}</strong>, a été employée à la société Djibouti Labour Service du <strong>{{DATE_EMBAUCHE}}</strong> au <strong>{{DATE_SORTIE}}</strong> sous contrat à durée indeterminée en qualité de <strong>{{FONCTION}}</strong> affecté au sein du port de <strong>{{CODE.SOCIETE}}</strong>.</p>\n"
            ."<p>{{NOM_COMPLET}} nous quitte ce jour, libre de tout engagement.</p>\n"
            ."<p>Le présent certificat est délivré à la demande de l'intéressé pour servir et valoir ce que de droit.</p>");

        $certificatAdministratif = $this->page('CERTIFICAT ADMINISTRATIF',
            "<p>La Direction des Ressources Humaines DLS, certifie que <strong>{{NOM_COMPLET}}</strong>, Fichier <strong>{{MATRICULE}}</strong> de fonction <strong>{{FONCTION}}</strong>, réunis <strong>{{ANCIENNETE}}</strong> ans et <strong>{{ANCIENNETE_MOIS}}</strong> mois de service, à la date du <strong>{{DATE_DERNIER_BULLETIN_DE_PAIE}}</strong>.</p>\n"
            ."<p>En cas d'une éventuelle rupture […] articles 10 et 20 Convention Collective 26/12/2011.</p>\n"
            ."<p><strong>Indemnité préavis</strong> catégorie <strong>{{CATEGORIE}}</strong> (1 mois salaire brut).<br>{{SALAIRE_BRUT}} FD x 1 mois = <strong>{{MONTANT_SAISIE_MANUELLE_1}}</strong></p>\n"
            ."<p><strong>Indemnité licenciement</strong> {{ANCIENNETE}} ans : {{SALAIRE_BRUT}} FD x {{ANCIENNETE}} x 15/30 = <strong>{{MONTANT_SAISIE_MANUELLE_2}}</strong></p>\n"
            ."<p>Retenue Retraite = <strong>{{MONTANT_SAISIE_MANUELLE_3}}</strong></p>\n"
            ."<p>Net à payer = <strong>{{MONTANT_SAISIE_MANUELLE_4}}</strong></p>\n"
            .'<p>Le droit se perd en cas de faute lourde. Etablie pour servir et valoir ce que de droit.</p>');

        $titreConge = $this->page('TITRE DE CONGE',
            $identite."\n"
            ."<p>Est autorisé à s'absenter de Djibouti Labour Services FZCo du <strong>{{DATE_DEBUT}}</strong> au <strong>{{DATE_FIN}}</strong></p>\n"
            ."<p>Pour raison : {{MOTIF}}<br>Date réelle retour : <strong>{{DATE_RETOUR}}</strong></p>\n"
            .'<table style="width:100%; border-collapse:collapse; text-align:center" border="1" cellpadding="6"><tr><th>Nb jours déjà pris</th><th>Nb jours demandés</th><th>Nb jours restant</th></tr><tr><td>{{JOURS_PRIS}}</td><td>{{NBRE_JOURS}}</td><td>{{JOURS_RESTANT}}</td></tr></table>'."\n"
            .'<p style="font-size:10px">Code Travail Article 103 : « Le congé doit être effectivement pris dans les douze mois […] »</p>',
            false,
            '<p style="text-align:left">Planning congé prévu le : <strong>{{ANNEE_CONGE}}</strong></p>'."\n<br>\n");

        $signatureAgent = "<p>Date demande : {{DATE_DEMANDE}}<br>Signature agent :</p>\n"
            .'<p style="margin-top:30px">ABSENCE AUTORISE &nbsp;&nbsp;/&nbsp;&nbsp; ABSENCE REFUSE<br>Date et signature responsable :</p>';

        $demandeAbsence = $this->page("DEMANDE D'AUTORISATION D'ABSENCE",
            "<p>Nom : <strong>{{NOM_COMPLET}}</strong><br>Fichier : <strong>{{MATRICULE}}</strong><br>Fonction : <strong>{{FONCTION}}</strong><br>Direction/Département : <strong>{{DEPARTEMENT.NAME}}</strong></p>\n"
            ."<p>Sollicite autorisation s'absenter DU <strong>{{DATE_DEBUT}}</strong> au <strong>{{DATE_FIN}}</strong><br>Nombre Jours : <strong>{{NBRE_JOURS}}</strong><br>Pour motif : {{MOTIF}}</p>\n"
            .$signatureAgent, false);

        $demandeAbsenceHeures = $this->page("DEMANDE D'AUTORISATION D'ABSENCE DURANT LES HEURES DE TRAVAIL",
            "<p>Nom : <strong>{{NOM_COMPLET}}</strong><br>Fichier : <strong>{{MATRICULE}}</strong><br>Fonction : <strong>{{FONCTION}}</strong><br>Direction/Département : <strong>{{DEPARTEMENT.NAME}}</strong></p>\n"
            ."<p>Journée : <strong>{{DATE_DEBUT}}</strong><br>Heure départ : <strong>{{HEURE_DEPART}}</strong><br>Heure retour : <strong>{{HEURE_RETOUR}}</strong><br>Nombre heures : <strong>{{NOMBRE_HEURES}}</strong></p>\n"
            ."<p>Pour motif : {{MOTIF}}</p>\n"
            .$signatureAgent, false);

        $sanction = $this->page('DECISION',
            "<p style=\"text-align:center\"><strong>Sanctionnant un agent en service au D.L.S</strong></p>\n"
            ."<p>VU La convention collective territoriale<br>VU Le Règlement Intérieur de <strong>{{CODE.SOCIETE}}</strong></p>\n"
            ."<p>Sur rapport demande d'explication du <strong>{{DATE_DEMANDE}}</strong>.</p>\n"
            ."<p>Est infligé à : <strong>{{NOM_COMPLET}}</strong><br>Fichier : <strong>{{MATRICULE}}</strong><br>Emploi : <strong>{{FONCTION}}</strong><br>Service : DLS/<strong>{{CODE.SOCIETE}}</strong></p>\n"
            ."<p>Sanction précédente : {{SANCTION_PRECEDENTE}}.<br>Sanction suivante : <strong>{{SANCTION_SUIVANTE}}</strong>.</p>\n"
            .'<p>Motif : {{MOTIF}} à la date du <strong>{{DATE_MOTIF}}</strong>.</p>');

        return [
            [
                'table_types' => 'types_documents', 'table_models' => 'model_documents', 'fk' => 'type_document_id',
                'types' => [
                    ['CT', 'CERTIFICAT DE TRAVAIL', []],
                    ['CA', 'CERTIFICAT ADMINISTRATIF', []],
                ],
                'models' => [
                    ['Certificat de travail', 9, $certificatTravail, ['CT']],
                    ['Certificat administratif', 1, $certificatAdministratif, ['CA']],
                ],
            ],
            [
                'table_types' => 'types_conges', 'table_models' => 'model_conges', 'fk' => 'type_conge_id',
                'types' => [
                    ['CONGE_ANNUEL', 'Congé annuel', ['color' => '#16a34a', 'default_days_per_year' => 30]],
                    ['CONGE_MATERNITE', 'Congé maternité', ['color' => '#db2777', 'requires_justificatif' => true]],
                    ['ABSENCE', "Autorisation d'absence", ['color' => '#6b7280']],
                    ['ABSENCE_HEURES', "Autorisation d'absence durant les heures de travail", ['color' => '#6b7280']],
                ],
                'models' => [
                    ['Titre de congé', 1, $titreConge, ['CONGE_ANNUEL', 'CONGE_MATERNITE']],
                    ["Demande d'autorisation d'absence", 1, $demandeAbsence, ['ABSENCE']],
                    ["Demande d'autorisation d'absence durant les heures de travail", 1, $demandeAbsenceHeures, ['ABSENCE_HEURES']],
                ],
            ],
            [
                'table_types' => 'types_sanctions', 'table_models' => 'model_sanctions', 'fk' => 'type_sanction_id',
                'types' => [
                    ['ABSENCE_INJUSTIFIEE', 'Absence injustifiée', []],
                    ['FAUTE_PROFESSIONNELLE', 'Faute professionnelle', ['requires_conseil_discipline' => true]],
                    ['FAUTE_INFRACTION', 'Faute infraction', ['requires_conseil_discipline' => true]],
                ],
                'models' => [
                    ['Décision sanctionnant un agent', 1, $sanction, ['ABSENCE_INJUSTIFIEE', 'FAUTE_PROFESSIONNELLE', 'FAUTE_INFRACTION']],
                ],
            ],
        ];
    }
};
