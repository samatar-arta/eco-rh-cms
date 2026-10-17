<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Conge;
use App\Models\Document;
use App\Models\Employe;
use App\Models\Evaluation;
use App\Models\ModelConge;
use App\Models\ModelDocument;
use App\Models\ModelEvaluation;
use App\Models\ModelMutation;
use App\Models\ModelPromotion;
use App\Models\ModelSanction;
use App\Models\Mutation;
use App\Models\Promotion;
use App\Models\Sanction;
use App\Models\TypeConge;
use App\Models\TypeDocument;
use App\Models\TypeEvaluation;
use App\Models\TypeMutation;
use App\Models\TypePromotion;
use App\Models\TypeSanction;
use App\Models\User;
use App\Services\CongeDayCounter;
use Carbon\Carbon;
use Database\Seeders\TypesEtModelesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TypesEtModelesSeeder::class);
    }

    /** @return array<string, array{0: class-string, 1: class-string, 2: class-string, 3: string, 4: string, 5: array<string, mixed>, 6: int}> */
    public static function modules(): array
    {
        return [
            'documents' => [Document::class, TypeDocument::class, ModelDocument::class, 'type_document_id', 'model_document_id', [], 6],
            'conges' => [Conge::class, TypeConge::class, ModelConge::class, 'type_conge_id', 'model_conge_id', ['date_debut' => '2026-01-05', 'date_fin' => '2026-01-06', 'nbre_jours' => 2], 6],
            'sanctions' => [Sanction::class, TypeSanction::class, ModelSanction::class, 'type_sanction_id', 'model_sanction_id', ['date_sanction' => '2026-01-05'], 3],
            'evaluations' => [Evaluation::class, TypeEvaluation::class, ModelEvaluation::class, 'type_evaluation_id', 'model_evaluation_id', ['date_evaluation' => '2026-01-05'], 4],
            'promotions' => [Promotion::class, TypePromotion::class, ModelPromotion::class, 'type_promotion_id', 'model_promotion_id', ['date_effet' => '2026-01-05'], 4],
            'mutations' => [Mutation::class, TypeMutation::class, ModelMutation::class, 'type_mutation_id', 'model_mutation_id', ['date_effet' => '2026-01-05'], 5],
        ];
    }

    #[DataProvider('modules')]
    public function test_seeded_types_each_have_exactly_one_default_model(string $record, string $type, string $model, string $typeFk, string $modelFk, array $attrs, int $count): void
    {
        $this->assertSame($count, $type::count());

        foreach ($type::all() as $t) {
            $this->assertSame(1, $model::where($typeFk, $t->id)->where('is_default', true)->count());
        }
    }

    #[DataProvider('modules')]
    public function test_record_accepts_matching_model_and_rejects_foreign_model(string $record, string $type, string $model, string $typeFk, string $modelFk, array $attrs): void
    {
        $employe = Employe::factory()->create();
        [$a, $b] = $type::query()->orderBy('id')->take(2)->get();
        $modelA = $model::where($typeFk, $a->id)->firstOrFail();
        $modelB = $model::where($typeFk, $b->id)->firstOrFail();
        $base = ['matricule' => $employe->matricule] + $attrs;

        $ok = $record::create($base + [$typeFk => $a->id, $modelFk => $modelA->id]);
        $this->assertTrue($ok->exists);

        $this->expectException(ValidationException::class);
        $record::create($base + [$typeFk => $a->id, $modelFk => $modelB->id]);
    }

    #[DataProvider('modules')]
    public function test_record_cannot_be_switched_to_a_foreign_model_afterwards(string $record, string $type, string $model, string $typeFk, string $modelFk, array $attrs): void
    {
        $employe = Employe::factory()->create();
        [$a, $b] = $type::query()->orderBy('id')->take(2)->get();
        $row = $record::create(['matricule' => $employe->matricule, $typeFk => $a->id] + $attrs);

        $this->expectException(ValidationException::class);
        $row->update([$modelFk => $model::where($typeFk, $b->id)->firstOrFail()->id]);
    }

    public function test_document_types_have_distinct_templates_and_renewal_rule_is_configured(): void
    {
        $attestation = TypeDocument::where('code', 'ATTESTATION_TRAVAIL')->firstOrFail();
        $certificat = TypeDocument::where('code', 'CERTIFICAT_TRAVAIL')->firstOrFail();

        $this->assertSame(3, $attestation->delai_renouvellement_mois);
        $this->assertNull($certificat->delai_renouvellement_mois);
        $this->assertNotEquals(
            $attestation->modeles->pluck('id')->all(),
            $certificat->modeles->pluck('id')->all()
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(TypesEtModelesSeeder::class);

        $this->assertSame(6, TypeDocument::count());
        $this->assertSame(6, ModelDocument::count());
    }

    public function test_template_version_increments_on_content_change_only(): void
    {
        $model = ModelDocument::firstOrFail();

        $model->update(['titre' => 'Nouveau titre']);
        $this->assertSame(1, $model->fresh()->version);

        $model->update(['contenu' => '<p>Nouveau</p>']);
        $this->assertSame(2, $model->fresh()->version);
    }

    public function test_only_one_default_template_per_type(): void
    {
        $type = TypeDocument::where('code', 'ATTESTATION_TRAVAIL')->firstOrFail();
        $first = $type->modeles()->firstOrFail();

        $second = ModelDocument::create([
            'type_document_id' => $type->id, 'titre' => 'Autre', 'contenu' => '<p>x</p>', 'is_default' => true,
        ]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_leave_end_date_cannot_precede_start_date(): void
    {
        $this->expectException(ValidationException::class);

        Conge::create([
            'matricule' => Employe::factory()->create()->matricule,
            'type_conge_id' => TypeConge::firstOrFail()->id,
            'date_debut' => '2026-03-10', 'date_fin' => '2026-03-09', 'nbre_jours' => 1,
        ]);
    }

    public function test_leave_day_counter_supports_calendar_and_working_days(): void
    {
        config(['rh.conges.jours_non_travailles' => [6, 7], 'rh.conges.jours_feries' => ['2026-03-11']]);
        $debut = Carbon::parse('2026-03-09'); // lundi
        $fin = Carbon::parse('2026-03-15');   // dimanche

        $counter = new CongeDayCounter;

        $this->assertSame(7.0, $counter->count($debut, $fin, 'calendaires'));
        $this->assertSame(4.0, $counter->count($debut, $fin, 'ouvres'));
    }

    public function test_reference_is_unique_across_records_of_a_module(): void
    {
        $employe = Employe::factory()->create();
        $type = TypeDocument::firstOrFail();
        Document::create(['matricule' => $employe->matricule, 'type_document_id' => $type->id, 'reference' => '0001/DA_DARH/DLS/2026']);

        $this->expectException(QueryException::class);
        Document::create(['matricule' => $employe->matricule, 'type_document_id' => $type->id, 'reference' => '0001/DA_DARH/DLS/2026']);
    }

    public function test_evaluator_must_be_manager_or_senior(): void
    {
        $employe = Employe::factory()->create();
        $type = TypeEvaluation::firstOrFail();
        $senior = User::factory()->create();
        $senior->assignRole(Role::RhSenior);
        $junior = User::factory()->create();
        $junior->assignRole(Role::RhJunior);
        $attrs = ['matricule' => $employe->matricule, 'type_evaluation_id' => $type->id, 'date_evaluation' => '2026-01-05'];

        $this->assertTrue(Evaluation::create($attrs + ['evaluateur_id' => $senior->id])->exists);

        $this->expectException(ValidationException::class);
        Evaluation::create($attrs + ['evaluateur_id' => $junior->id]);
    }

    public function test_evaluation_score_is_weighted_by_criteria_coefficients(): void
    {
        $type = TypeEvaluation::firstOrFail();
        $modele = $type->modeles()->firstOrFail();
        $evaluation = Evaluation::create([
            'matricule' => Employe::factory()->create()->matricule,
            'type_evaluation_id' => $type->id,
            'model_evaluation_id' => $modele->id,
            'date_evaluation' => '2026-01-05',
            'resultats_criteres' => [
                ['libelle' => 'Qualité du travail', 'note' => 16],            // coef 2
                ['libelle' => 'Ponctualité et assiduité', 'note' => 12],      // coef 1
                ['libelle' => "Esprit d'équipe", 'note' => 20],               // coef 1
                ['libelle' => 'Critère inconnu', 'note' => 20],               // ignoré
            ],
        ]);

        // (16*2 + 12 + 20) / 4 = 16
        $this->assertSame(16.0, $evaluation->calculerNote());
    }
}
