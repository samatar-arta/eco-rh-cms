<?php

namespace Database\Seeders;

use App\Models\ModelConge;
use App\Models\ModelDocument;
use App\Models\ModelEvaluation;
use App\Models\ModelMutation;
use App\Models\ModelPromotion;
use App\Models\ModelSanction;
use App\Models\NiveauSanction;
use App\Models\TypeConge;
use App\Models\TypeDocument;
use App\Models\TypeEvaluation;
use App\Models\TypeMutation;
use App\Models\TypePromotion;
use App\Models\TypeSanction;
use Illuminate\Database\Seeder;

/**
 * Types métier et un modèle HTML par défaut par type. Idempotent : ne touche
 * pas aux types/modèles déjà présents (donc pas aux modifications des admins).
 */
class TypesEtModelesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed(TypeDocument::class, ModelDocument::class, 'type_document_id', [
            ['ATTESTATION_TRAVAIL', 'Attestation de travail', ['delai_renouvellement_mois' => 3]],
            ['CERTIFICAT_TRAVAIL', 'Certificat de travail', []],
            ['ATTESTATION_SALAIRE', 'Attestation de salaire', ['delai_renouvellement_mois' => 3]],
            ['ATTESTATION_CONGE', 'Attestation de congé', []],
            ['DECISION_ADMINISTRATIVE', 'Décision administrative', []],
            ['CERTIFICAT_ADMINISTRATIF', 'Certificat administratif', []],
        ], "<p>Référence : {{ document.reference }}</p>\n<p>Le Département des Ressources Humaines de DLS atteste que {{ employe.name }} (matricule {{ employe.matricule }}), {{ employe.fonction }}, est employé(e) depuis le {{ employe.date_embauche }}.</p>\n<p>Fait le {{ document.date_production }}.</p>");

        $this->seed(TypeConge::class, ModelConge::class, 'type_conge_id', [
            ['CONGE_ANNUEL', 'Congé annuel', ['color' => '#16a34a', 'default_days_per_year' => 30]],
            ['CONGE_MALADIE', 'Congé maladie', ['color' => '#dc2626', 'requires_justificatif' => true]],
            ['CONGE_EXCEPTIONNEL', 'Congé exceptionnel', ['color' => '#d97706', 'requires_justificatif' => true]],
            ['CONGE_SANS_SOLDE', 'Congé sans solde', ['color' => '#6b7280']],
            ['CONGE_MATERNITE', 'Congé maternité', ['color' => '#db2777', 'requires_justificatif' => true]],
            ['CONGE_PATERNITE', 'Congé paternité', ['color' => '#2563eb', 'requires_justificatif' => true]],
        ], '<p>Décision de congé pour {{ employe.name }} (matricule {{ employe.matricule }}) du {{ conge.date_debut }} au {{ conge.date_fin }}, soit {{ conge.nbre_jours }} jour(s).</p>');

        $this->seed(TypeSanction::class, ModelSanction::class, 'type_sanction_id', [
            ['ABSENCE_INJUSTIFIEE', 'Absence injustifiée', []],
            ['FAUTE_PROFESSIONNELLE', 'Faute professionnelle', ['requires_conseil_discipline' => true]],
            ['FAUTE_INFRACTION', 'Faute infraction', ['requires_conseil_discipline' => true]],
        ], "<p>Notification de sanction à {{ employe.name }} (matricule {{ employe.matricule }}) en date du {{ sanction.date_sanction }}.</p>\n<p>{{ sanction.description }}</p>");

        $this->seed(TypeEvaluation::class, ModelEvaluation::class, 'type_evaluation_id', [
            ['EVALUATION_ANNUELLE', 'Évaluation annuelle', []],
            ['EVALUATION_ESSAI', "Évaluation de période d'essai", []],
            ['EVALUATION_PERFORMANCE', 'Évaluation de performance', []],
            ['EVALUATION_EXCEPTIONNELLE', 'Évaluation exceptionnelle', []],
        ], '<p>Évaluation de {{ employe.name }} ({{ employe.matricule }}) du {{ evaluation.date_evaluation }}. Note : {{ evaluation.note }}.</p>', [
            'criteres' => [
                ['libelle' => 'Qualité du travail', 'coefficient' => 2, 'note_max' => 20],
                ['libelle' => 'Ponctualité et assiduité', 'coefficient' => 1, 'note_max' => 20],
                ['libelle' => 'Esprit d\'équipe', 'coefficient' => 1, 'note_max' => 20],
            ],
        ]);

        $this->seed(TypePromotion::class, ModelPromotion::class, 'type_promotion_id', [
            ['PROMOTION_GRADE', 'Promotion de grade', []],
            ['PROMOTION_FONCTION', 'Promotion de fonction', []],
            ['PROMOTION_SALARIALE', 'Promotion salariale', []],
            ['PROMOTION_GRADE_FONCTION', 'Promotion de grade et fonction', []],
        ], '<p>{{ employe.name }} ({{ employe.matricule }}) est promu(e) de {{ promotion.ancienne_fonction }} à {{ promotion.nouvelle_fonction }}.</p>');

        $this->seed(TypeMutation::class, ModelMutation::class, 'type_mutation_id', [
            ['MUTATION_SITE', 'Mutation de site', []],
            ['MUTATION_DEPARTEMENT', 'Mutation de département', []],
            ['MUTATION_FONCTION', 'Mutation de fonction', []],
            ['MUTATION_SOCIETE', 'Mutation de société', []],
            ['MUTATION_SITE_DEPARTEMENT', 'Mutation de site-département', []],
        ], '<p>{{ employe.name }} ({{ employe.matricule }}) est muté(e) de {{ mutation.ancienne_fonction }} à {{ mutation.nouvelle_fonction }}.</p>');

        foreach ([
            ['AVERTISSEMENT', 'Avertissement', 1],
            ['BLAME', 'Blâme', 2],
            ['MISE_A_PIED', 'Mise à pied', 3],
            ['LICENCIEMENT', 'Licenciement', 4],
        ] as [$code, $name, $ordre]) {
            NiveauSanction::firstOrCreate(['code' => $code], ['name' => $name, 'ordre' => $ordre]);
        }
    }

    /**
     * @param  class-string  $typeClass
     * @param  class-string  $modelClass
     * @param  list<array{0: string, 1: string, 2: array<string, mixed>}>  $types
     * @param  array<string, mixed>  $modelExtra
     */
    private function seed(string $typeClass, string $modelClass, string $fk, array $types, string $corps, array $modelExtra = []): void
    {
        foreach ($types as [$code, $name, $extra]) {
            $type = $typeClass::firstOrCreate(['code' => $code], ['name' => $name] + $extra);

            if (! $modelClass::where($fk, $type->id)->exists()) {
                $modelClass::create([
                    $fk => $type->id,
                    'titre' => $name,
                    'contenu' => "<h1>{$name}</h1>\n{$corps}",
                    'is_default' => true,
                ] + $modelExtra);
            }
        }
    }
}
