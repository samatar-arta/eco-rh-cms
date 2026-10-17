<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\WorkflowStatut;
use App\Exceptions\WorkflowException;
use App\Models\EmployeAffectation;
use App\Models\Evaluation;
use App\Models\ModelEvaluation;
use App\Models\Mutation;
use App\Models\Promotion;
use App\Models\SiteDepartement;
use App\Models\TypeEvaluation;
use App\Models\TypeMutation;
use App\Models\TypePromotion;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDossiers;
use Tests\TestCase;

class PromotionsMutationsEffectsTest extends TestCase
{
    use CreatesDossiers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTypes();
        $this->travelTo('2026-03-01 10:00:00');
    }

    /** Un Senior crée, un autre valide, le Manager approuve (séparation des tâches). */
    private function approuver($record): void
    {
        $w = app(WorkflowService::class);
        $w->soumettre($record, $this->acteur(Role::RhSenior));
        $w->valider($record, $this->acteur(Role::RhSenior));
        $w->approuver($record, $this->acteur(Role::RhManager));
    }

    private function promotion(string $code, array $extra = []): Promotion
    {
        return Promotion::create($extra + [
            'matricule' => $this->employe->matricule,
            'type_promotion_id' => TypePromotion::where('code', $code)->firstOrFail()->id,
            'date_effet' => '2026-03-01',
        ]);
    }

    private function mutation(string $code, array $extra = []): Mutation
    {
        $e = $this->employe;

        return Mutation::create($extra + [
            'matricule' => $e->matricule,
            'type_mutation_id' => TypeMutation::where('code', $code)->firstOrFail()->id,
            'ancien_site_id' => $e->site_id, 'ancien_departement_id' => $e->departement_id,
            'ancienne_societe_id' => $e->societe_id, 'ancienne_fonction' => $e->fonction,
            'date_effet' => '2026-03-01',
        ]);
    }

    public function test_promotion_requires_fields_for_its_type(): void
    {
        $this->expectException(ValidationException::class);
        app(WorkflowService::class)->soumettre($this->promotion('PROMOTION_GRADE_FONCTION', ['nouveau_grade' => 'G3']), $this->acteur(Role::RhSenior));
    }

    public function test_employee_is_untouched_until_final_approval(): void
    {
        $promotion = $this->promotion('PROMOTION_FONCTION', ['nouvelle_fonction' => 'Chef equipe']);
        $avant = $this->employe->fonction;
        $w = app(WorkflowService::class);

        $w->soumettre($promotion, $this->acteur(Role::RhSenior));
        $w->valider($promotion, $this->acteur(Role::RhSenior));
        $this->assertSame($avant, $this->employe->fresh()->fonction);

        $w->rejeter($promotion, $this->acteur(Role::RhManager), 'Non justifié');
        $this->assertSame($avant, $this->employe->fresh()->fonction);
        $this->assertNull($promotion->fresh()->effet_applique_le);
    }

    public function test_approved_promotion_updates_employee_and_keeps_history(): void
    {
        $avant = $this->employe->fonction;
        $promotion = $this->promotion('PROMOTION_GRADE_FONCTION', ['nouvelle_fonction' => 'Superviseur', 'nouveau_grade' => 'G4']);

        $this->approuver($promotion);

        $e = $this->employe->fresh();
        $this->assertSame('Superviseur', $e->fonction);
        $this->assertSame('G4', $e->grade);
        $this->assertNotNull($promotion->fresh()->effet_applique_le);

        $historique = EmployeAffectation::where('employe_id', $e->id)->orderBy('id')->get();
        $this->assertCount(2, $historique);
        $this->assertSame($avant, $historique[0]->fonction);
        $this->assertNotNull($historique[0]->date_fin);
        $this->assertNull($historique[1]->date_fin);
        $this->assertSame('Superviseur', $historique[1]->fonction);
        $this->assertContains('effets_appliques', $promotion->workflow->histories->pluck('etape')->all());
    }

    public function test_future_effect_date_is_deferred_then_applied_by_the_scheduler(): void
    {
        $promotion = $this->promotion('PROMOTION_FONCTION', ['nouvelle_fonction' => 'Directeur', 'date_effet' => '2026-04-01']);
        $avant = $this->employe->fonction;

        $this->approuver($promotion);
        $this->assertSame(WorkflowStatut::Approuve, $promotion->workflow->fresh()->statut);
        $this->assertNotNull($promotion->fresh()->reference);
        $this->assertSame($avant, $this->employe->fresh()->fonction);

        $this->artisan('rh:appliquer-effets')->assertSuccessful();
        $this->assertSame($avant, $this->employe->fresh()->fonction);

        $this->travelTo('2026-04-01 00:30:00');
        $this->artisan('rh:appliquer-effets')->assertSuccessful();
        $this->assertSame('Directeur', $this->employe->fresh()->fonction);

        // Idempotent : un second passage ne duplique pas l'historique.
        $this->artisan('rh:appliquer-effets')->assertSuccessful();
        $this->assertSame(2, EmployeAffectation::where('employe_id', $this->employe->id)->count());
    }

    public function test_approved_mutation_moves_the_employee_and_preserves_history(): void
    {
        $dest = SiteDepartement::factory()->create();
        $avant = $this->employe->only(['site_id', 'departement_id', 'societe_id']);
        $mutation = $this->mutation('MUTATION_SITE_DEPARTEMENT', [
            'nouveau_site_id' => $dest->site_id, 'nouveau_departement_id' => $dest->departement_id,
            'nouvelle_societe_id' => $dest->societe_id, 'nouvelle_fonction' => 'Technicien',
        ]);

        $this->approuver($mutation);

        $e = $this->employe->fresh();
        $this->assertSame($dest->site_id, $e->site_id);
        $this->assertSame($dest->departement_id, $e->departement_id);
        $this->assertSame($dest->societe_id, $e->societe_id);
        $this->assertSame('Technicien', $e->fonction);

        $ancienne = EmployeAffectation::where('employe_id', $e->id)->whereNotNull('date_fin')->first();
        $this->assertSame($avant['site_id'], $ancienne->site_id);
        $this->assertSame($avant['departement_id'], $ancienne->departement_id);
        $this->assertSame(2, EmployeAffectation::where('employe_id', $e->id)->count());
    }

    public function test_mutation_without_real_change_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        app(WorkflowService::class)->soumettre(
            $this->mutation('MUTATION_FONCTION', ['nouvelle_fonction' => $this->employe->fonction]),
            $this->acteur(Role::RhSenior),
        );
    }

    public function test_mutation_with_department_of_another_company_is_refused(): void
    {
        $autre = SiteDepartement::factory()->create();

        $this->expectException(ValidationException::class);
        app(WorkflowService::class)->soumettre(
            $this->mutation('MUTATION_DEPARTEMENT', ['nouveau_departement_id' => $autre->departement_id]),
            $this->acteur(Role::RhSenior),
        );
    }

    public function test_stale_mutation_is_reported_once_as_conflict_when_deferred(): void
    {
        $dest = SiteDepartement::factory()->create();
        $mutation = $this->mutation('MUTATION_SITE', ['nouveau_site_id' => $dest->site_id, 'date_effet' => '2026-04-01']);
        $this->approuver($mutation);

        // L'employé est déplacé entre-temps.
        $this->employe->update(['site_id' => SiteDepartement::factory()->create()->site_id]);

        $this->travelTo('2026-04-02 00:10:00');
        $this->artisan('rh:appliquer-effets')->assertFailed();
        $this->artisan('rh:appliquer-effets')->assertFailed();

        $this->assertNull($mutation->fresh()->effet_applique_le);
        $this->assertSame(1, $mutation->workflow->histories()->where('etape', 'effets_en_conflit')->count());
    }

    public function test_stale_mutation_blocks_the_approval_itself_when_due(): void
    {
        $dest = SiteDepartement::factory()->create();
        $mutation = $this->mutation('MUTATION_SITE', ['nouveau_site_id' => $dest->site_id]);
        $w = app(WorkflowService::class);
        $w->soumettre($mutation, $this->acteur(Role::RhSenior));
        $w->valider($mutation, $this->acteur(Role::RhSenior));
        $manager = $this->acteur(Role::RhManager);
        $ailleurs = SiteDepartement::factory()->create([
            'societe_id' => $this->employe->societe_id, 'departement_id' => $this->employe->departement_id,
        ]);
        $this->employe->update(['site_id' => $ailleurs->site_id]);
        $manager->siteDepartements()->attach($ailleurs);

        try {
            $w->approuver($mutation, $manager);
            $this->fail('Approbation sur données périmées.');
        } catch (WorkflowException) {
            $this->assertSame(WorkflowStatut::EnAttenteApprobation, $mutation->workflow->fresh()->statut);
            $this->assertNull($mutation->fresh()->reference, 'Aucune référence consommée.');
        }
    }

    public function test_evaluation_note_is_computed_server_side_from_weighted_criteria(): void
    {
        $type = TypeEvaluation::firstOrFail();
        $modele = ModelEvaluation::where('type_evaluation_id', $type->id)->firstOrFail();

        $evaluation = Evaluation::create([
            'matricule' => $this->employe->matricule, 'type_evaluation_id' => $type->id,
            'model_evaluation_id' => $modele->id, 'evaluateur_id' => $this->acteur(Role::RhSenior)->id,
            'date_evaluation' => '2026-03-01', 'note' => 20, // valeur forcée par le navigateur
            'resultats_criteres' => [
                ['libelle' => 'Qualité du travail', 'note' => 16],
                ['libelle' => 'Ponctualité et assiduité', 'note' => 12],
                ['libelle' => "Esprit d'équipe", 'note' => 20],
            ],
        ]);

        $this->assertSame('16.00', $evaluation->fresh()->note);
    }
}
