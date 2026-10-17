<?php

namespace Tests\Feature;

use App\Enums\RhModule;
use App\Enums\Role;
use App\Enums\WorkflowStatut;
use App\Exceptions\WorkflowException;
use App\Filament\Cms\Resources\ModelDocuments\Pages\ManageModelDocuments;
use App\Models\Conge;
use App\Models\ModelDocument;
use App\Models\ReferenceSequence;
use App\Models\TypeConge;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\DocumentGenerationService;
use App\Services\PdfGenerationService;
use App\Services\TemplateRendererService;
use App\Services\WorkflowService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Tests\Concerns\CreatesDossiers;
use Tests\TestCase;

class DocumentGenerationTest extends TestCase
{
    use CreatesDossiers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTypes();
        $this->travelTo('2026-03-01 10:00:00');
    }

    /** Parcours complet Junior → Senior → Manager ; renvoie le dossier rafraîchi. */
    private function approuver(Model $record): Model
    {
        $service = app(WorkflowService::class);
        $service->soumettre($record, $this->acteur(Role::RhJunior));
        $service->valider($record, $this->acteur(Role::RhSenior));
        $service->approuver($record, $this->acteur(Role::RhManager));

        return $record->fresh();
    }

    private function conge(): Conge
    {
        return Conge::create([
            'matricule' => $this->employe->matricule,
            'type_conge_id' => TypeConge::firstOrFail()->id,
            'date_debut' => '2026-03-10', 'date_fin' => '2026-03-12', 'nbre_jours' => 3,
            'fichier_path' => 'conges/justificatif.pdf',
        ]);
    }

    // --- rendu --------------------------------------------------------------

    public function test_renderer_replaces_whitelisted_variables_and_escapes_values(): void
    {
        $this->employe->update(['name' => '<b>Mallicieux</b> & Co']);
        $rendu = app(TemplateRendererService::class)->rendre(
            '<p>{{ employe.name }} / {{ employe.matricule }} / {{ date_du_jour }} / [{{ secret.env }}]</p>',
            $this->document(),
            $this->employe->fresh()
        );

        $this->assertStringContainsString('&lt;b&gt;Mallicieux&lt;/b&gt; &amp; Co', $rendu);
        $this->assertStringContainsString($this->employe->matricule, $rendu);
        $this->assertStringContainsString('01/03/2026', $rendu);
        $this->assertStringContainsString('[]', $rendu, 'Une variable hors liste blanche donne une valeur vide.');
    }

    public function test_renderer_never_executes_code_and_strips_active_content(): void
    {
        $rendu = app(TemplateRendererService::class)->rendre(
            '<p onclick="x()">A</p><script>alert(1)</script><?php echo "pwned"; ?><iframe src="http://x"></iframe><a href="javascript:evil()">l</a>{{ system("ls") }}',
            $this->document()
        );

        foreach (['script', 'onclick', 'pwned', 'iframe', 'javascript:', 'system'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $rendu);
        }
        $this->assertStringContainsString('<p', $rendu);
    }

    public function test_unknown_variables_are_reported(): void
    {
        $this->assertSame(
            ['employe.password'],
            app(TemplateRendererService::class)->variablesInconnues('{{ employe.name }} {{ employe.password }}')
        );
    }

    public function test_template_form_refuses_unknown_variables(): void
    {
        Filament::setCurrentPanel('cms');
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);

        Livewire::actingAs($admin->fresh())->test(ManageModelDocuments::class)
            ->callAction('create', [
                'type_document_id' => $this->typeDocument()->id,
                'titre' => 'Piégé',
                'contenu' => '<p>{{ employe.salaire_secret }}</p>',
                'is_active' => true,
            ])
            ->assertHasFormErrors(['contenu']);

        $this->assertSame(0, ModelDocument::where('titre', 'Piégé')->count());
    }

    private function typeDocument(): TypeDocument
    {
        return TypeDocument::where('code', 'ATTESTATION_TRAVAIL')->firstOrFail();
    }

    // --- finalisation ------------------------------------------------------

    public function test_approval_produces_a_frozen_signed_archived_pdf(): void
    {
        $brouillon = $this->document();
        $brouillon->update(['model_document_id' => $this->typeDocument()->modeles()->where('is_default', true)->value('id')]);
        $document = $this->approuver($brouillon);

        $this->assertSame('0001/DA_DARH/DLS/2026', $document->reference);
        $this->assertNotNull($document->fichier_path);
        Storage::disk('local')->assertExists($document->fichier_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($document->fichier_path));

        $this->assertStringContainsString($this->employe->name, $document->contenu_genere);
        $this->assertNotNull($document->model_version);
        $this->assertSame('2026-03-01', $document->date_production->toDateString());

        $etapes = $document->workflow->histories()->pluck('etape')->all();
        $this->assertContains('generation_pdf', $etapes);
        $this->assertContains('signature', $etapes);
        $this->assertContains('archivage', $etapes);
    }

    public function test_pdf_failure_keeps_the_approval_and_a_retry_never_changes_the_reference(): void
    {
        $etat = new \stdClass;
        $etat->appels = 0;
        $this->app->bind(PdfGenerationService::class, fn () => new class($etat) extends PdfGenerationService
        {
            public function __construct(private \stdClass $etat) {}

            public function depuisHtml(string $html): string
            {
                if ($this->etat->appels++ === 0) {
                    throw new RuntimeException('moteur PDF indisponible');
                }

                return parent::depuisHtml($html);
            }
        });

        $document = $this->approuver($this->document());

        $this->assertSame(WorkflowStatut::Approuve, $document->workflow->statut);
        $this->assertSame('0001/DA_DARH/DLS/2026', $document->reference);
        $this->assertNull($document->fichier_path);
        $this->assertContains('echec_pdf', $document->workflow->histories()->pluck('etape')->all());
        $gele = $document->contenu_genere;

        app(DocumentGenerationService::class)->finaliser($document);

        $document = $document->fresh();
        $this->assertSame('0001/DA_DARH/DLS/2026', $document->reference);
        $this->assertSame($gele, $document->contenu_genere);
        $this->assertNotNull($document->fichier_path);
        $this->assertSame(1, (int) ReferenceSequence::where('module', 'DOCUMENT')->value('dernier_numero'));
    }

    public function test_finalisation_is_idempotent(): void
    {
        $document = $this->approuver($this->document());
        $chemin = $document->fichier_path;

        $this->assertSame($chemin, app(DocumentGenerationService::class)->finaliser($document));
        $this->assertSame(1, $document->workflow->histories()->where('etape', 'generation_pdf')->count());
    }

    public function test_command_finalises_approved_dossiers_missing_their_pdf(): void
    {
        $this->app->bind(PdfGenerationService::class, fn () => new class extends PdfGenerationService
        {
            public function depuisHtml(string $html): string
            {
                throw new RuntimeException('panne');
            }
        });
        $document = $this->approuver($this->document());
        $this->assertNull($document->fichier_path);

        $this->app->forgetInstance(PdfGenerationService::class);
        $this->app->bind(PdfGenerationService::class, fn () => new PdfGenerationService);

        $this->artisan('dossiers:finaliser')->assertSuccessful();

        $this->assertNotNull($document->fresh()->fichier_path);
        $this->assertSame('0001/DA_DARH/DLS/2026', $document->fresh()->reference);
    }

    public function test_finalised_document_is_immutable_even_if_the_template_changes(): void
    {
        $document = $this->approuver($this->document());
        $gele = $document->contenu_genere;

        ModelDocument::findOrFail($document->model_document_id ?? $this->typeDocument()->modeles()->first()->id)
            ->update(['contenu' => '<p>Nouveau texte {{ employe.name }}</p>']);

        $this->assertSame($gele, $document->fresh()->contenu_genere);

        foreach ([['contenu_genere' => 'altéré'], ['model_version' => 99], ['fichier_path' => 'autre.pdf']] as $changement) {
            try {
                $document->fresh()->update($changement);
                $this->fail('Modification d\'un document finalisé acceptée : '.key($changement));
            } catch (LogicException) {
            }
        }
    }

    public function test_leave_decision_pdf_does_not_overwrite_the_justificatif(): void
    {
        $conge = $this->approuver($this->conge());

        $this->assertSame('conges/justificatif.pdf', $conge->fichier_path);
        $this->assertNotNull($conge->decision_path);
        $this->assertStringContainsString('/conge/', $conge->decision_path);
        $this->assertStringStartsWith('0001/CG_DARH/DLS', $conge->reference);
    }

    // --- règle des trois mois ----------------------------------------------

    public function test_renewal_delay_blocks_a_second_official_production_of_the_same_type(): void
    {
        $this->typeDocument()->update(['delai_renouvellement_mois' => 3]);
        $this->approuver($this->document());

        try {
            app(WorkflowService::class)->soumettre($this->document(), $this->acteur(Role::RhJunior));
            $this->fail('Le délai de 3 mois aurait dû bloquer la soumission.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('3 mois', $e->getMessage());
            $this->assertStringContainsString('01/06/2026', $e->getMessage());
        }

        $this->travelTo('2026-06-02 10:00:00');
        $this->assertSame('0002/DA_DARH/DLS/2026', $this->approuver($this->document())->reference);
    }

    public function test_renewal_is_enforced_at_approval_even_if_both_were_submitted_before(): void
    {
        $this->typeDocument()->update(['delai_renouvellement_mois' => 3]);
        $service = app(WorkflowService::class);
        $junior = $this->acteur(Role::RhJunior);
        $senior = $this->acteur(Role::RhSenior);
        $manager = $this->acteur(Role::RhManager);

        $premier = $this->document();
        $second = $this->document();
        foreach ([$premier, $second] as $d) {
            $service->soumettre($d, $junior);
            $service->valider($d, $senior);
        }

        $service->approuver($premier, $manager);

        $this->expectException(WorkflowException::class);
        try {
            $service->approuver($second, $manager);
        } finally {
            $this->assertNull($second->fresh()->reference);
            $this->assertSame(WorkflowStatut::EnAttenteApprobation, $second->workflow->fresh()->statut);
            $this->assertSame(1, (int) ReferenceSequence::where('module', 'DOCUMENT')->value('dernier_numero'));
        }
    }

    public function test_renewal_rule_is_not_applied_to_types_without_a_delay_nor_to_other_types(): void
    {
        $this->typeDocument()->update(['delai_renouvellement_mois' => 3]);
        $this->approuver($this->document('ATTESTATION_TRAVAIL'));

        $this->assertSame('0002/DA_DARH/DLS/2026', $this->approuver($this->document('CERTIFICAT_TRAVAIL'))->reference);

        $this->typeDocument()->update(['delai_renouvellement_mois' => null]);
        $this->assertSame('0003/DA_DARH/DLS/2026', $this->approuver($this->document('ATTESTATION_TRAVAIL'))->reference);
    }

    // --- téléchargement ----------------------------------------------------

    public function test_download_requires_authentication_and_the_dossier_perimeter(): void
    {
        $document = $this->approuver($this->document());
        $url = route('dossiers.fichier', ['document', $document->id]);

        $this->get($url)->assertRedirect();

        $reponse = $this->actingAs($this->acteur(Role::RhJunior))->get($url);
        $reponse->assertOk();
        $this->assertSame('nosniff', $reponse->headers->get('X-Content-Type-Options'));

        $this->actingAs($this->acteur(Role::RhJunior, dansLePerimetre: false))->get($url)->assertForbidden();
        $this->actingAs($this->acteur(Role::RhJunior))->get(route('dossiers.fichier', ['inconnu', $document->id]))->assertNotFound();
    }

    public function test_download_of_a_missing_file_and_of_a_leave_proof(): void
    {
        $this->actingAs($this->acteur(Role::RhJunior));
        $document = $this->document();
        $this->get(route('dossiers.fichier', ['document', $document->id]))->assertNotFound();

        Storage::disk('local')->put('conges/justificatif.pdf', '%PDF-justificatif');
        $conge = $this->conge();
        $this->get(route('dossiers.fichier', ['conge', $conge->id, 'justificatif']))->assertOk();
        $this->get(route('dossiers.fichier', ['document', $document->id, 'justificatif']))->assertNotFound();
    }

    public function test_download_of_a_disciplinary_pdf_is_denied_to_a_junior_who_did_not_create_it(): void
    {
        $sanction = $this->sanction();
        $sanction->forceFill(['cree_par' => $this->acteur(Role::RhOfficer)->id, 'fichier_path' => 'dossiers/sanction/x.pdf'])->save();
        Storage::disk('local')->put('dossiers/sanction/x.pdf', '%PDF');

        $this->actingAs($this->acteur(Role::RhJunior))
            ->get(route('dossiers.fichier', [RhModule::Sanction->morphAlias(), $sanction->id]))
            ->assertForbidden();
        $this->actingAs($this->acteur(Role::RhOfficer))
            ->get(route('dossiers.fichier', [RhModule::Sanction->morphAlias(), $sanction->id]))
            ->assertOk();
    }

    public function test_only_a_manager_in_perimeter_can_request_a_pdf_retry(): void
    {
        $this->app->bind(PdfGenerationService::class, fn () => new class extends PdfGenerationService
        {
            public function depuisHtml(string $html): string
            {
                throw new RuntimeException('panne');
            }
        });
        $document = $this->approuver($this->document());

        $this->assertTrue($this->acteur(Role::RhManager)->can('regenerer', $document));
        $this->assertFalse($this->acteur(Role::RhManager, dansLePerimetre: false)->can('regenerer', $document));
        $this->assertFalse($this->acteur(Role::RhSenior)->can('regenerer', $document));
    }
}
