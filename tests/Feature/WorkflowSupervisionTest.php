<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\WorkflowHistories\Pages\ListWorkflowHistories;
use App\Filament\Resources\Workflows\Pages\ListWorkflows;
use App\Models\Document;
use App\Models\Employe;
use App\Models\TypeDocument;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowHistory;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesDossiers;
use Tests\TestCase;

class WorkflowSupervisionTest extends TestCase
{
    use CreatesDossiers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTypes();
        Filament::setCurrentPanel('rh');
    }

    private function workflowDe(string $statut, ?int $creePar, ?Employe $employe = null): Workflow
    {
        $doc = Document::create([
            'matricule' => ($employe ?? $this->employe)->matricule,
            'type_document_id' => TypeDocument::firstOrFail()->id,
        ]);

        return Workflow::create([
            'workflowable_type' => 'document', 'workflowable_id' => $doc->id,
            'statut' => $statut, 'cree_par' => $creePar,
        ]);
    }

    public function test_delays_are_configured_in_config_workflow(): void
    {
        $this->assertSame(48, config('workflow.delai_validation_heures'));
        $this->assertSame(48, config('workflow.delai_approbation_heures'));
        $this->assertNull(config('rh.workflow'));
    }

    public function test_check_delays_command_exists_and_keeps_its_old_name(): void
    {
        $this->artisan('workflow:check-delays')->assertSuccessful();
        $this->artisan('workflow:retards')->assertSuccessful();
    }

    public function test_workflow_visibility_depends_on_role_and_perimeter(): void
    {
        $officer = $this->acteur(Role::RhOfficer);
        $junior = $this->acteur(Role::RhJunior);
        $autre = $this->acteur(Role::RhOfficer);

        $enValidation = $this->workflowDe('EN_ATTENTE_VALIDATION', $autre->id);
        $approuve = $this->workflowDe('APPROUVE', $autre->id);
        $dOfficer = $this->workflowDe('BROUILLON', $officer->id);
        $dJunior = $this->workflowDe('BROUILLON', $junior->id);
        $ailleurs = $this->workflowDe('EN_ATTENTE_VALIDATION', $autre->id, Employe::factory()->create());

        $ids = fn (User $u) => Workflow::query()->visibleTo($u)->pluck('id')->sort()->values()->all();
        $de = fn (Workflow ...$ws) => collect($ws)->pluck('id')->sort()->values()->all();

        $this->assertSame($de($enValidation, $approuve, $dOfficer, $dJunior, $ailleurs), $ids($this->acteur(Role::Admin, false)));
        $this->assertSame($de($enValidation, $approuve, $dOfficer, $dJunior, $ailleurs), $ids($this->acteur(Role::RhManager)));
        $this->assertSame($de($enValidation), $ids($this->acteur(Role::RhSenior)));
        $this->assertSame($de($dOfficer), $ids($officer));
        $this->assertSame($de($dJunior), $ids($junior));
        $this->assertSame([], $ids($this->acteur(Role::GestionnairePaie)));
        // RH_MANAGER = ADMIN : tous les périmètres, même sans affectation.
        $this->assertSame($de($enValidation, $approuve, $dOfficer, $dJunior, $ailleurs), $ids($this->acteur(Role::RhManager, false)));
        $this->assertSame([], $ids($this->acteur(Role::RhSenior, false)));
    }

    public function test_workflow_screen_is_open_to_rh_roles_but_not_to_payroll(): void
    {
        foreach ([Role::RhManager, Role::RhSenior, Role::RhOfficer, Role::RhJunior] as $role) {
            $this->actingAs($this->acteur($role))->get('/rh/workflows')->assertOk();
        }
        $this->actingAs($this->acteur(Role::GestionnairePaie))->get('/rh/workflows')->assertForbidden();
    }

    public function test_table_shows_every_perimeter_to_the_manager_is_filterable_and_read_only(): void
    {
        $manager = $this->acteur(Role::RhManager);
        $visible = $this->workflowDe('REJETE', null);
        $cache = $this->workflowDe('REJETE', null, Employe::factory()->create());

        Livewire::actingAs($manager)->test(ListWorkflows::class)
            ->assertCanSeeTableRecords([$visible])
            ->assertCanSeeTableRecords([$cache])
            ->filterTable('statut', 'APPROUVE')->assertCanNotSeeTableRecords([$visible])
            ->resetTableFilters()
            ->filterTable('workflowable_type', 'document')->assertCanSeeTableRecords([$visible]);

        $this->assertTrue($manager->can('view', $visible));
        $this->assertTrue($manager->can('view', $cache));
        $this->assertFalse($this->acteur(Role::RhSenior)->can('view', $cache));
        $this->assertFalse($manager->can('update', $visible));
        $this->assertFalse($manager->can('delete', $visible));
    }

    public function test_morph_map_and_uniqueness_of_workflowable(): void
    {
        $w = $this->workflowDe('BROUILLON', null);
        $this->assertSame('document', $w->getRawOriginal('workflowable_type'));
        $this->assertInstanceOf(Document::class, $w->workflowable);

        $this->expectException(QueryException::class);
        Workflow::create(['workflowable_type' => 'document', 'workflowable_id' => $w->workflowable_id]);
    }

    public function test_only_admin_and_manager_see_the_history_and_it_is_immutable(): void
    {
        foreach ([Role::RhSenior, Role::RhOfficer, Role::RhJunior, Role::GestionnairePaie] as $role) {
            $this->actingAs($this->acteur($role))->get('/rh/workflow-histories')->assertForbidden();
        }
        $this->actingAs($this->acteur(Role::RhManager))->get('/rh/workflow-histories')->assertOk();

        $history = WorkflowHistory::create([
            'workflow_id' => $this->workflowDe('BROUILLON', null)->id,
            'etape' => 'creation', 'statut' => 'BROUILLON', 'date_action' => now(),
        ]);
        $admin = $this->acteur(Role::Admin);
        $this->assertFalse($admin->can('update', $history));
        $this->assertFalse($admin->can('delete', $history));

        Livewire::actingAs($admin)->test(ListWorkflowHistories::class)->assertCanSeeTableRecords([$history]);
    }
}
