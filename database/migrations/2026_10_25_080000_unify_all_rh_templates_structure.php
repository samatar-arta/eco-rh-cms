<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uniformise les modèles HTML des six modules RH sur la structure finale :
 * contenu = REF + titre + corps + clôture + bloc de signature DARH
 * ([[SIGNATURE_DARH]]). L'en-tête et le pied de page bleus (#002060) ne sont PAS
 * dans le contenu : ils viennent de resources/views/pdf/dossier.blade.php.
 *
 * Crée les types manquants (par code) puis un modèle par (type, titre).
 * Idempotent : un modèle n'est inséré que s'il est absent et mis à jour seulement
 * si sa version est inférieure à la version cible (une édition faite depuis
 * l'interface n'est jamais écrasée). Les dossiers déjà finalisés gardent leur
 * contenu figé. Les textes sont des propositions à valider par la DARH.
 */
return new class extends Migration
{
    private const REF = '<p style="text-align:left">REF : {{REFERENCE}} <span style="float:right">Djibouti, le {{DATE_PRODUCTION}}</span></p>';

    private const SIGNATURE = '<p style="text-align:right">Directrice de l\'Administration et des Ressources Humaines<br><br><strong>FATOUMA DAHIR OUMAR</strong><br><br>[[SIGNATURE_DARH]]</p>';

    private const CLOTURE_DEFAUT = 'La présente est établie pour servir et valoir ce que de droit.';

    private const SOUSSIGNEE = 'Je soussignée, Directrice de l\'Administration et des Ressources Humaines de Djibouti Labour Services (DLS), ';

    public function up(): void
    {
        // Données d'exploitation uniquement : les tests partent des seeders et ne
        // doivent pas hériter de ces types/modèles (premier type/modèle trouvé, etc.).
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

                foreach ($module['models'] as [$titre, $version, $corps, $cloture, $codes]) {
                    foreach ($codes ?? array_keys($ids) as $code) {
                        if (isset($ids[$code])) {
                            $this->modele($module['table_models'], $module['fk'], $ids[$code], $titre, $version, $this->contenu($titre, $corps, $cloture));
                        }
                    }
                }
            }
        });
    }

    /** Irréversible : les types/modèles créés peuvent être référencés par des dossiers. */
    public function down(): void {}

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

    private function contenu(string $titre, string $corps, string $cloture): string
    {
        return '<div style="font-family: Arial; padding: 40px;">'."\n".self::REF."\n<br><br>\n"
            .'<h2 style="text-align:center; text-decoration:underline">'.mb_strtoupper($titre).'</h2>'."\n<br><br>\n"
            .$corps."\n<p>".$cloture."</p>\n<br><br><br>\n".self::SIGNATURE."\n</div>";
    }

    /** @return list<array<string, mixed>> */
    private function modules(): array
    {
        $s = self::SOUSSIGNEE;
        $id = '<strong>{{NOM_COMPLET}}</strong>, fichier <strong>{{MATRICULE}}</strong>';
        $poste = '<strong>{{POSTE}}</strong> au sein de la direction <strong>{{DIRECTION}}</strong>';
        $c = self::CLOTURE_DEFAUT;
        $att = 'La présente attestation est délivrée à l\'intéressé pour servir et valoir ce que de droit.';

        return [
            [
                'table_types' => 'types_documents',
                'table_models' => 'model_documents', 'fk' => 'type_document_id',
                'types' => [
                    ['AT', 'ATTESTATION DE TRAVAIL', []],
                    ['AMT', 'ATTESTATION DE MISSION DE TRAVAIL', []],
                    ['CT', 'CERTIFICAT DE TRAVAIL', []],
                    ['AS', 'ATTESTATION DE SALAIRE', ['delai_renouvellement_mois' => 3]],
                ],
                'models' => [
                    ['Attestation de travail', 9, '<p>'.$s.'atteste que <strong>{{NOM_COMPLET}}</strong>, fichier <strong>{{MATRICULE}}</strong> est employé à la société Djibouti Labour Services (DLS) depuis le <strong>{{DATE_EMBAUCHE}}</strong> sous contrat à durée indéterminée.</p>'."\n".'<p>L\'intéressé occupe actuellement la fonction de <strong>{{FONCTION}}</strong> au sein de la société.</p>', $att, ['AT']],
                    ['Attestation de mission de travail', 9, '<p>'.$s.'atteste par la présente que :</p>'."\n".'<p><strong>{{NOM_COMPLET}}</strong>, Fichier : <strong>{{MATRICULE}}</strong> a été mis à la disposition de la société <strong>{{CODE}}</strong> à compter de l\'an <strong>{{ANNEE_EMBAUCHE}}</strong> en qualité d\'agent journalier pour le poste de <strong>{{FONCTION}}</strong>.</p>', $att, ['AMT']],
                    ['Certificat de travail', 1, '<p>'.$s.'certifie que '.$id.', a été employé(e) au sein de la société Djibouti Labour Services (DLS) du <strong>{{DATE_EMBAUCHE}}</strong> au <strong>{{DATE_SORTIE}}</strong>, soit une durée de <strong>{{DUREE}}</strong>, en qualité de '.$poste.'.</p>'."\n".'<p>L\'intéressé(e) quitte la société libre de tout engagement.</p>', 'Le présent certificat est délivré à l\'intéressé(e) pour servir et valoir ce que de droit.', ['CT']],
                    ['Attestation de salaire', 1, '<p>'.$s.'atteste que '.$id.', employé(e) à la société Djibouti Labour Services (DLS) depuis le <strong>{{DATE_EMBAUCHE}}</strong> en qualité de '.$poste.', perçoit un salaire mensuel de <strong>[MONTANT_SALAIRE]</strong>.</p>', $att, ['AS']],
                ],
            ],
            [
                'table_types' => 'types_conges',
                'table_models' => 'model_conges', 'fk' => 'type_conge_id',
                'types' => [
                    ['CONGE_ANNUEL', 'Congé annuel', ['color' => '#16a34a', 'default_days_per_year' => 30]],
                    ['CONGE_MATERNITE', 'Congé maternité', ['color' => '#db2777', 'requires_justificatif' => true]],
                ],
                'models' => [
                    ['Décision de congé annuel', 1, '<p>'.$s.'décide d\'accorder à '.$id.', '.$poste.', un congé annuel de <strong>{{DUREE}}</strong>, du <strong>{{DATE_DEBUT}}</strong> au <strong>{{DATE_FIN}}</strong> inclus.</p>'."\n".'<p>L\'intéressé(e) reprendra ses fonctions à l\'issue de cette période.</p>', 'La présente décision prend effet à compter de sa date de signature.', ['CONGE_ANNUEL']],
                    ['Décision de congé maternité', 1, '<p>'.$s.'décide d\'accorder à '.$id.', '.$poste.', un congé de maternité de <strong>{{DUREE}}</strong>, du <strong>{{DATE_DEBUT}}</strong> au <strong>{{DATE_FIN}}</strong> inclus, conformément à la législation du travail en vigueur en République de Djibouti.</p>', 'La présente décision prend effet à compter de sa date de signature.', ['CONGE_MATERNITE']],
                    ['Attestation de reprise de service', 1, '<p>'.$s.'atteste que '.$id.', '.$poste.', a repris ses fonctions à l\'issue de son congé du <strong>{{DATE_DEBUT}}</strong> au <strong>{{DATE_FIN}}</strong>.</p>', $att, ['CONGE_ANNUEL', 'CONGE_MATERNITE']],
                ],
            ],
            [
                'table_types' => 'types_evaluations',
                'table_models' => 'model_evaluations', 'fk' => 'type_evaluation_id',
                'types' => [
                    ['EVALUATION_ANNUELLE', 'Évaluation annuelle', []],
                    ['EVALUATION_ESSAI', 'Évaluation de période d\'essai', []],
                    ['EVALUATION_PERFORMANCE', 'Évaluation de performance', []],
                    ['EVALUATION_EXCEPTIONNELLE', 'Évaluation exceptionnelle', []],
                ],
                'models' => [
                    ['Notification d\'évaluation', 1, '<p>'.$s.'notifie à '.$id.', '.$poste.', les résultats de son évaluation réalisée le <strong>{{DATE_EVALUATION}}</strong> au titre de la période du <strong>{{PERIODE_DEBUT}}</strong> au <strong>{{PERIODE_FIN}}</strong>.</p>'."\n".'<p>Note finale obtenue : <strong>{{NOTE}}</strong>.</p>'."\n".'<p>Appréciation : {{APPRECIATION}}</p>', 'La présente notification est remise à l\'intéressé(e) et versée à son dossier.', null],
                    ['Attestation de performance', 1, '<p>'.$s.'atteste que '.$id.', '.$poste.', a fait l\'objet d\'une évaluation le <strong>{{DATE_EVALUATION}}</strong> et a obtenu la note de <strong>{{NOTE}}</strong>. {{APPRECIATION}}</p>', $att, ['EVALUATION_ANNUELLE', 'EVALUATION_PERFORMANCE']],
                ],
            ],
            [
                'table_types' => 'types_mutations',
                'table_models' => 'model_mutations', 'fk' => 'type_mutation_id',
                'types' => [
                    ['MUTATION_SITE', 'Mutation de site', []],
                    ['MUTATION_DEPARTEMENT', 'Mutation de département', []],
                    ['MUTATION_FONCTION', 'Mutation de fonction', []],
                    ['MUTATION_SOCIETE', 'Mutation de société', []],
                    ['MUTATION_SITE_DEPARTEMENT', 'Mutation de site-département', []],
                ],
                'models' => [
                    ['Décision de mutation interne', 1, '<p>'.$s.'décide de la mutation de '.$id.', actuellement <strong>{{ANCIENNE_FONCTION}}</strong> (direction <strong>{{ANCIEN_DEPARTEMENT}}</strong>), vers la fonction de <strong>{{NOUVELLE_FONCTION}}</strong> (direction <strong>{{NOUVEAU_DEPARTEMENT}}</strong>), avec effet au <strong>{{DATE_EFFET}}</strong>.</p>'."\n".'<p>Motif : {{MOTIF}}</p>', 'La présente décision prend effet à la date indiquée ci-dessus.', null],
                    ['Ordre d\'affectation', 1, '<p>'.$s.'ordonne l\'affectation de '.$id.' du site <strong>{{ANCIEN_SITE}}</strong> vers le site <strong>{{NOUVEAU_SITE}}</strong>, en qualité de <strong>{{NOUVELLE_FONCTION}}</strong>, à compter du <strong>{{DATE_EFFET}}</strong>.</p>'."\n".'<p>Motif : {{MOTIF}}</p>', 'L\'intéressé(e) est tenu(e) de rejoindre son nouveau poste à la date indiquée.', null],
                ],
            ],
            [
                'table_types' => 'types_promotions',
                'table_models' => 'model_promotions', 'fk' => 'type_promotion_id',
                'types' => [
                    ['PROMOTION_GRADE', 'Promotion de grade', []],
                    ['PROMOTION_FONCTION', 'Promotion de fonction', []],
                    ['PROMOTION_SALARIALE', 'Promotion salariale', []],
                    ['PROMOTION_GRADE_FONCTION', 'Promotion de grade et fonction', []],
                ],
                'models' => [
                    ['Décision de promotion', 1, '<p>'.$s.'décide de promouvoir '.$id.', de la fonction de <strong>{{ANCIENNE_FONCTION}}</strong> (grade <strong>{{ANCIEN_GRADE}}</strong>) à la fonction de <strong>{{NOUVELLE_FONCTION}}</strong> (grade <strong>{{NOUVEAU_GRADE}}</strong>), avec effet au <strong>{{DATE_EFFET}}</strong>.</p>'."\n".'<p>Motif : {{MOTIF}}</p>', 'La présente décision prend effet à la date indiquée ci-dessus.', null],
                    ['Notification d\'avancement', 1, '<p>'.$s.'notifie à '.$id.' son avancement à la fonction de <strong>{{NOUVELLE_FONCTION}}</strong> (grade <strong>{{NOUVEAU_GRADE}}</strong>) à compter du <strong>{{DATE_EFFET}}</strong>.</p>', 'La présente notification est remise à l\'intéressé(e) et versée à son dossier.', null],
                ],
            ],
            [
                'table_types' => 'types_sanctions',
                'table_models' => 'model_sanctions', 'fk' => 'type_sanction_id',
                'types' => [
                    ['ABSENCE_INJUSTIFIEE', 'Absence injustifiée', []],
                    ['FAUTE_PROFESSIONNELLE', 'Faute professionnelle', ['requires_conseil_discipline' => true]],
                    ['FAUTE_INFRACTION', 'Faute infraction', ['requires_conseil_discipline' => true]],
                ],
                'models' => [
                    ['Décision de sanction', 1, '<p>'.$s.'notifie à '.$id.', '.$poste.', la sanction de <strong>{{NIVEAU_SANCTION}}</strong> prononcée le <strong>{{DATE_SANCTION}}</strong>.</p>'."\n".'<p>Motif : {{MOTIF}}</p>'."\n".'<p>{{DESCRIPTION}}</p>', $c, null],
                    ['Avertissement écrit', 1, '<p>'.$s.'adresse un avertissement écrit à '.$id.', '.$poste.', pour les faits suivants, constatés le <strong>{{DATE_SANCTION}}</strong> :</p>'."\n".'<p>{{MOTIF}}</p>'."\n".'<p>Toute récidive pourra donner lieu à une sanction plus sévère.</p>', 'Le présent avertissement est versé au dossier de l\'intéressé(e).', null],
                    ['Mise à pied', 1, '<p>'.$s.'décide la mise à pied de '.$id.', '.$poste.', pour les faits suivants, constatés le <strong>{{DATE_SANCTION}}</strong> :</p>'."\n".'<p>{{MOTIF}}</p>'."\n".'<p>Durée de la mesure : <strong>{{DUREE}}</strong>, à compter du <strong>{{DATE_DEBUT}}</strong>.</p>', 'La présente décision est versée au dossier de l\'intéressé(e).', null],
                ],
            ],
        ];
    }
};
