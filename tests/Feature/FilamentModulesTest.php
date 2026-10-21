<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\WorkflowStatut;
use App\Filament\Cms\Resources\TypesDocuments\Pages\ManageTypesDocuments;
use App\Filament\Resources\Conges\Pages\CreateConge;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Employes\Pages\CreateEmploye;
use App\Filament\Resources\Mutations\Pages\CreateMutation;
use App\Filament\Resources\Sanctions\Pages\ListSanctions;
use App\Models\Conge;
use App\Models\Document;
use App\Models\Employe;
use App\Models\ModelDocument;
use App\Models\Mutation;
use App\Models\SiteDepartement;
use App\Models\TypeConge;
use App\Models\TypeDocument;
use App\Models\TypeMutation;
use App\Models\User;
use App\Services\UserAdministrationService;
use App\Services\WorkflowService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesDossiers;
use Tests\TestCase;

class FilamentModulesTest extends TestCase
{
    use CreatesDossiers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTypes();
        $this->travelTo('2026-03-01 10:00:00');
        Filament::setCurrentPanel('rh');
    }

    private function typeDocumentId(string $code = 'ATTESTATION_TRAVAIL'): int
    {
        return TypeDocument::where('code', $code)->firstOrFail()->id;
    }

    public function test_junior_creates_a_draft_without_reference(): void
    {
        $junior = $this->acteur(Role::RhJunior);
        $type = $this->typeDocumentId();
        $modele = ModelDocument::where('type_document_id', $type)->firstOrFail();

        Livewire::actingAs($junior)->test(CreateDocument::class)
            ->fillForm(['matricule' => $this->employe->matricule, 'type_document_id' => $type, 'model_document_id' => $modele->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = Document::firstOrFail();
        $this->assertSame($junior->id, $document->cree_par);
        $this->assertNull($document->reference);
        $this->assertSame(WorkflowStatut::Brouillon, $document->workflow->statut);
    }

    public function test_create_and_submit_enters_the_workflow(): void
    {
        $junior = $this->acteur(Role::RhJunior);
        $type = $this->typeDocumentId();

        Livewire::actingAs($junior)->test(CreateDocument::class)
            ->fillForm(['matricule' => $this->employe->matricule, 'type_document_id' => $type])
            ->set('soumettreApresCreation', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(WorkflowStatut::EnAttenteValidation, Document::firstOrFail()->workflow->statut);
    }

    public function test_a_template_of_another_type_is_refused_by_the_server(): void
    {
        $junior = $this->acteur(Role::RhJunior);
        $autreModele = ModelDocument::where('type_document_id', $this->typeDocumentId('CERTIFICAT_TRAVAIL'))->firstOrFail();

        Livewire::actingAs($junior)->test(CreateDocument::class)
            ->fillForm([
                'matricule' => $this->employe->matricule,
                'type_document_id' => $this->typeDocumentId('ATTESTATION_TRAVAIL'),
                'model_document_id' => $autreModele->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['model_document_id']);

        $this->assertSame(0, Document::count());
    }

    public function test_changing_the_type_proposes_only_that_types_default_template(): void
    {
        $junior = $this->acteur(Role::RhJunior);
        $type = $this->typeDocumentId('CERTIFICAT_TRAVAIL');

        $component = Livewire::actingAs($junior)->test(CreateDocument::class)
            ->fillForm(['type_document_id' => $type]);

        $attendu = ModelDocument::where('type_document_id', $type)->where('is_default', true)->value('id');
        $this->assertSame($attendu, (int) $component->get('data.model_document_id'));
    }

    public function test_employee_outside_the_perimeter_is_refused(): void
    {
        $junior = $this->acteur(Role::RhJunior, dansLePerimetre: false);

        Livewire::actingAs($junior)->test(CreateDocument::class)
            ->fillForm(['matricule' => $this->employe->matricule, 'type_document_id' => $this->typeDocumentId()])
            ->call('create')
            ->assertHasFormErrors(['matricule']);

        $this->assertSame(0, Document::count());
    }

    public function test_list_only_shows_records_of_the_users_perimeter(): void
    {
        $visible = $this->document();
        $autreSd = SiteDepartement::factory()->create();
        $autreEmploye = Employe::factory()->create([
            'societe_id' => $autreSd->societe_id, 'departement_id' => $autreSd->departement_id, 'site_id' => $autreSd->site_id,
        ]);
        $cache = Document::create(['matricule' => $autreEmploye->matricule, 'type_document_id' => $this->typeDocumentId()]);

        Livewire::actingAs($this->acteur(Role::RhOfficer))->test(ListDocuments::class)
            ->assertCanSeeTableRecords([$visible])
            ->assertCanNotSeeTableRecords([$cache]);
    }

    public function test_record_page_of_another_perimeter_is_not_reachable(): void
    {
        $autreSd = SiteDepartement::factory()->create();
        $autreEmploye = Employe::factory()->create([
            'societe_id' => $autreSd->societe_id, 'departement_id' => $autreSd->departement_id, 'site_id' => $autreSd->site_id,
        ]);
        $cache = Document::create(['matricule' => $autreEmploye->matricule, 'type_document_id' => $this->typeDocumentId()]);

        $this->actingAs($this->acteur(Role::RhOfficer))->get("/rh/documents/{$cache->id}")->assertNotFound();
        $this->actingAs($this->acteur(Role::RhOfficer))->get("/rh/documents/{$cache->id}/edit")->assertNotFound();
    }

    public function test_workflow_actions_follow_role_and_status(): void
    {
        $document = $this->document();
        app(WorkflowService::class)->soumettre($document, $this->acteur(Role::RhJunior));
        $senior = $this->acteur(Role::RhSenior);

        Livewire::actingAs($this->acteur(Role::RhJunior))->test(ListDocuments::class)
            ->assertActionHidden(TestAction::make('valider')->table($document))
            ->assertActionHidden(TestAction::make('approuver')->table($document));

        Livewire::actingAs($this->acteur(Role::RhManager))->test(ListDocuments::class)
            ->assertActionHidden(TestAction::make('approuver')->table($document));

        Livewire::actingAs($senior)->test(ListDocuments::class)
            ->assertActionVisible(TestAction::make('valider')->table($document))
            ->callAction(TestAction::make('valider')->table($document), ['commentaire' => 'OK']);

        $this->assertSame(WorkflowStatut::EnAttenteApprobation, $document->workflow->fresh()->statut);

        Livewire::actingAs($this->acteur(Role::RhManager))->test(ListDocuments::class)
            ->callAction(TestAction::make('approuver')->table($document));

        $this->assertSame('0001/DA_DARH/DLS/2026', $document->fresh()->reference);
    }

    public function test_pages_render_and_the_view_page_shows_the_timeline(): void
    {
        $document = $this->document();
        app(WorkflowService::class)->soumettre($document, $this->acteur(Role::RhJunior));
        $senior = $this->acteur(Role::RhSenior);

        $this->actingAs($senior);
        $this->get('/rh/documents')->assertOk();
        $this->get('/rh/documents/create')->assertOk();
        $this->get("/rh/documents/{$document->id}")->assertOk()->assertSee('Historique du workflow')->assertSee('soumission');
        $this->get("/rh/documents/{$document->id}/edit")->assertForbidden();
        foreach (['conges', 'sanctions', 'evaluations', 'promotions', 'mutations', 'employes'] as $page) {
            $this->get("/rh/{$page}")->assertOk();
        }
    }

    public function test_rejection_from_the_interface_requires_a_comment(): void
    {
        $document = $this->document();
        app(WorkflowService::class)->soumettre($document, $this->acteur(Role::RhJunior));

        Livewire::actingAs($this->acteur(Role::RhSenior))->test(ListDocuments::class)
            ->callAction(TestAction::make('rejeter')->table($document), ['commentaire' => ''])
            ->assertHasFormErrors(['commentaire' => 'required']);

        $this->assertSame(WorkflowStatut::EnAttenteValidation, $document->workflow->fresh()->statut);
    }

    public function test_a_submitted_document_can_no_longer_be_edited_or_deleted(): void
    {
        $document = $this->document();
        $junior = $this->acteur(Role::RhJunior);
        app(WorkflowService::class)->soumettre($document, $junior);

        $this->assertFalse($junior->can('update', $document->fresh()));
        $this->assertFalse($junior->can('delete', $document->fresh()));
    }

    public function test_disciplinary_records_are_restricted(): void
    {
        $sanction = $this->sanction();
        $sanction->forceFill(['cree_par' => $this->acteur(Role::RhOfficer)->id])->save();

        $junior = $this->acteur(Role::RhJunior);
        $officer = $this->acteur(Role::RhOfficer);

        $this->assertFalse($junior->can('view', $sanction));
        $this->assertTrue($officer->can('view', $sanction));

        Livewire::actingAs($junior)->test(ListSanctions::class)->assertCanNotSeeTableRecords([$sanction]);
        Livewire::actingAs($officer)->test(ListSanctions::class)->assertCanSeeTableRecords([$sanction]);
    }

    public function test_leave_days_are_computed_by_the_server(): void
    {
        $junior = $this->acteur(Role::RhJunior);
        $type = TypeConge::firstOrFail();

        Livewire::actingAs($junior)->test(CreateConge::class)
            ->fillForm([
                'matricule' => $this->employe->matricule,
                'type_conge_id' => $type->id,
                'date_debut' => '2026-03-02',
                'date_fin' => '2026-03-04',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $conge = Conge::firstOrFail();
        $this->assertSame(3.0, (float) $conge->nbre_jours);
        $this->assertSame($junior->id, $conge->user_id);
    }

    public function test_mutation_freezes_old_assignment_and_leaves_the_employee_untouched(): void
    {
        $manager = $this->acteur(Role::RhManager);
        $destination = SiteDepartement::factory()->create();
        $avant = $this->employe->only(['site_id', 'departement_id', 'societe_id', 'fonction']);

        Livewire::actingAs($manager)->test(CreateMutation::class)
            ->fillForm([
                'matricule' => $this->employe->matricule,
                'type_mutation_id' => TypeMutation::firstOrFail()->id,
                'date_effet' => '2026-04-01',
                'nouveau_site_id' => $destination->site_id,
                'nouvelle_societe_id' => $destination->societe_id,
                'nouveau_departement_id' => $destination->departement_id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $mutation = Mutation::firstOrFail();
        $this->assertSame($avant['site_id'], $mutation->ancien_site_id);
        $this->assertSame($avant['departement_id'], $mutation->ancien_departement_id);
        $this->assertSame($avant['fonction'], $mutation->ancienne_fonction);
        $this->assertSame($avant, $this->employe->fresh()->only(['site_id', 'departement_id', 'societe_id', 'fonction']));
    }

    public function test_junior_cannot_create_a_mutation(): void
    {
        $this->assertFalse($this->acteur(Role::RhJunior)->can('create', Mutation::class));
        $this->assertTrue($this->acteur(Role::RhSenior)->can('create', Mutation::class));
    }

    public function test_employee_creation_requires_an_affectation_in_the_users_perimeter(): void
    {
        $officer = $this->acteur(Role::RhOfficer);
        $hors = SiteDepartement::factory()->create();

        Livewire::actingAs($officer)->test(CreateEmploye::class)
            ->fillForm([
                'matricule' => 'M-NEW-1', 'name' => 'Nouvel Employé', 'statut_employe' => 'ACTIF',
                'societe_id' => $hors->societe_id, 'departement_id' => $hors->departement_id, 'site_id' => $hors->site_id,
            ])
            ->call('create')
            ->assertHasFormErrors(['site_id']);

        Livewire::actingAs($officer)->test(CreateEmploye::class)
            ->fillForm([
                'matricule' => 'M-NEW-2', 'name' => 'Nouvel Employé', 'statut_employe' => 'ACTIF',
                'societe_id' => $this->perimetre->societe_id, 'departement_id' => $this->perimetre->departement_id,
                'site_id' => $this->perimetre->site_id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($officer->id, Employe::where('matricule', 'M-NEW-2')->value('cree_par'));
    }

    public function test_cms_admin_screens_are_admin_only(): void
    {
        Filament::setCurrentPanel('cms');
        $this->actingAs($this->acteur(Role::RhManager))->get('/cms/types-documents')->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);

        Livewire::actingAs($admin->fresh())->test(ManageTypesDocuments::class)
            ->callAction('create', ['code' => 'NOUVEAU_TYPE', 'name' => 'Nouveau type', 'requires_model' => true, 'requires_workflow' => true, 'is_active' => true])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('types_documents', ['code' => 'NOUVEAU_TYPE']);
    }

    public function test_an_admin_cannot_widen_their_own_rights_nor_deactivate_themselves(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);
        $admin = $admin->fresh();
        $service = app(UserAdministrationService::class);

        $service->modifier($admin, $admin, ['roles' => ['ADMIN', 'RH_MANAGER'], 'perimetres' => [$this->perimetre->id], 'is_active' => false]);

        $admin = $admin->fresh();
        $this->assertSame([Role::Admin], $admin->rolesEnum());
        $this->assertSame(0, $admin->siteDepartements()->count());
        $this->assertTrue($admin->is_active);
    }

    public function test_only_admin_can_create_users_and_assign_roles(): void
    {
        $service = app(UserAdministrationService::class);
        $payload = ['name' => 'Nouveau', 'email' => 'nouveau@example.com', 'password' => 'un-mot-de-passe-long'];

        try {
            $service->creer($this->acteur(Role::RhManager), $payload);
            $this->fail('Seul un ADMIN crée des utilisateurs.');
        } catch (AuthorizationException) {
        }

        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);
        $user = $service->creer($admin->fresh(), $payload + ['roles' => ['RH_JUNIOR'], 'perimetres' => [$this->perimetre->id]]);

        $this->assertSame([Role::RhJunior], $user->fresh()->rolesEnum());
        $this->assertSame(1, $user->siteDepartements()->count());
        $this->assertTrue($user->is_active);
    }
}
