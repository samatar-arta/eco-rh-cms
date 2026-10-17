<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\WorkflowStatut;
use App\Events\DossierApprouve;
use App\Exceptions\WorkflowException;
use App\Models\Workflow;
use App\Models\WorkflowHistory;
use App\Services\WorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\CreatesDossiers;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    use CreatesDossiers, RefreshDatabase;

    private WorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTypes();
        $this->travelTo('2026-03-01 10:00:00');
        $this->service = app(WorkflowService::class);
    }

    public function test_full_cycle_assigns_the_reference_only_at_final_approval(): void
    {
        Event::fake([DossierApprouve::class]);
        $document = $this->document();
        $junior = $this->acteur(Role::RhJunior);
        $senior = $this->acteur(Role::RhSenior);
        $manager = $this->acteur(Role::RhManager);

        $this->service->soumettre($document, $junior);
        $this->assertSame(WorkflowStatut::EnAttenteValidation, $document->workflow->fresh()->statut);

        $this->service->valider($document, $senior);
        $this->assertSame(WorkflowStatut::EnAttenteApprobation, $document->workflow->fresh()->statut);
        $this->assertNull($document->fresh()->reference);

        $workflow = $this->service->approuver($document, $manager);

        $this->assertSame(WorkflowStatut::Approuve, $workflow->statut);
        $this->assertSame('0001/DA_DARH/DLS/2026', $document->fresh()->reference);
        $this->assertSame($senior->id, $workflow->valide_par);
        $this->assertSame($manager->id, $workflow->approuve_par);
        $this->assertSame(
            ['creation', 'soumission', 'attente_validation', 'validation', 'attente_approbation', 'approbation', 'attribution_reference'],
            $workflow->histories()->orderBy('id')->pluck('etape')->all()
        );
        $this->assertSame('document', $workflow->workflowable_type);
        Event::assertDispatched(DossierApprouve::class);
    }

    public function test_a_draft_cannot_jump_to_approved(): void
    {
        $document = $this->document();
        $this->service->ouvrir($document, $this->acteur(Role::RhOfficer));

        $this->expectException(WorkflowException::class);
        $this->service->approuver($document, $this->acteur(Role::RhManager));
    }

    public function test_roles_are_enforced_at_each_step(): void
    {
        $document = $this->document();
        $junior = $this->acteur(Role::RhJunior);
        $this->service->soumettre($document, $junior);

        try {
            $this->service->valider($document, $this->acteur(Role::RhJunior));
            $this->fail('Un junior ne valide pas.');
        } catch (AuthorizationException) {
        }

        $this->service->valider($document, $this->acteur(Role::RhSenior));

        $this->expectException(AuthorizationException::class);
        $this->service->approuver($document, $this->acteur(Role::RhSenior));
    }

    public function test_actor_outside_the_perimeter_is_refused(): void
    {
        $document = $this->document();
        $this->service->soumettre($document, $this->acteur(Role::RhJunior));

        $this->expectException(AuthorizationException::class);
        $this->service->valider($document, $this->acteur(Role::RhSenior, dansLePerimetre: false));
    }

    public function test_rejection_requires_a_comment_keeps_history_and_allows_resubmission(): void
    {
        $document = $this->document();
        $junior = $this->acteur(Role::RhJunior);
        $senior = $this->acteur(Role::RhSenior);
        $this->service->soumettre($document, $junior);

        try {
            $this->service->rejeter($document, $senior, '   ');
            $this->fail('Commentaire obligatoire.');
        } catch (ValidationException) {
        }

        $this->service->rejeter($document, $senior, 'Matricule erroné');
        $workflow = $document->workflow->fresh();
        $this->assertSame(WorkflowStatut::Rejete, $workflow->statut);
        $this->assertSame('Matricule erroné', $workflow->histories()->where('etape', 'rejet')->value('commentaire'));

        $this->service->soumettre($document, $junior);
        $workflow = $document->workflow->fresh();
        $this->assertSame(WorkflowStatut::EnAttenteValidation, $workflow->statut);
        $this->assertNotNull($workflow->histories()->where('etape', 'rejet')->first(), "L'historique du rejet est conservé.");
        $this->assertNotNull($workflow->histories()->where('etape', 'reprise')->first());
    }

    public function test_a_second_validation_of_the_same_dossier_is_refused(): void
    {
        $document = $this->document();
        $this->service->soumettre($document, $this->acteur(Role::RhJunior));
        $this->service->valider($document, $this->acteur(Role::RhSenior));

        $this->expectException(WorkflowException::class);
        $this->service->valider($document, $this->acteur(Role::RhSenior));
    }

    public function test_creator_cannot_validate_or_approve_their_own_dossier(): void
    {
        $promotion = $this->promotion();
        $senior = $this->acteur(Role::RhSenior);
        $this->service->soumettre($promotion, $senior);

        $this->expectException(AuthorizationException::class);
        $this->service->valider($promotion, $senior);
    }

    public function test_approver_cannot_be_the_validator(): void
    {
        $promotion = $this->promotion();
        $this->service->soumettre($promotion, $this->acteur(Role::RhSenior));
        $double = $this->acteur(Role::RhSenior);
        $double->assignRole(Role::RhManager);
        $this->service->valider($promotion, $double);

        $this->expectException(AuthorizationException::class);
        $this->service->approuver($promotion, $double->fresh());
    }

    public function test_evaluations_promotions_and_mutations_are_restricted_to_manager_and_senior(): void
    {
        $promotion = $this->promotion();

        try {
            $this->service->ouvrir($promotion, $this->acteur(Role::RhJunior));
            $this->fail('Un junior ne crée pas de promotion.');
        } catch (AuthorizationException) {
        }

        $this->assertNotNull($this->service->ouvrir($promotion, $this->acteur(Role::RhManager)));
    }

    public function test_deadlines_are_set_and_overdue_dossiers_are_detected(): void
    {
        $document = $this->document();
        $this->service->soumettre($document, $this->acteur(Role::RhJunior));
        $workflow = $document->workflow->fresh();

        $this->assertTrue($workflow->date_limite_validation->equalTo(now()->addHours(48)));
        $this->assertFalse($workflow->estEnRetard());
        $this->assertSame(0, Workflow::enRetard()->count());

        $this->travel(49)->hours();

        $this->assertTrue($workflow->fresh()->estEnRetard());
        $this->assertSame(1, Workflow::enRetard()->count());

        $this->service->valider($document, $this->acteur(Role::RhSenior));
        $timeline = $document->workflow->fresh()->timeline();
        $validation = $timeline->firstWhere('etape', 'validation');

        $this->assertTrue($validation['en_retard']);
        $this->assertSame(49 * 60, $validation['duree_minutes']);
    }

    public function test_history_cannot_be_modified_or_deleted(): void
    {
        $document = $this->document();
        $this->service->ouvrir($document, $this->acteur(Role::RhJunior));
        $entry = WorkflowHistory::firstOrFail();

        try {
            $entry->update(['commentaire' => 'trafiqué']);
            $this->fail('Modification refusée.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_cannot_reuse_a_workflow_on_two_records_and_each_record_has_one_workflow(): void
    {
        $document = $this->document();
        $junior = $this->acteur(Role::RhJunior);

        $premier = $this->service->ouvrir($document, $junior);
        $second = $this->service->ouvrir($document, $junior);

        $this->assertTrue($premier->is($second));
        $this->assertSame(1, Workflow::count());
    }
}
