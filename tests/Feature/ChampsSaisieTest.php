<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Exceptions\WorkflowException;
use App\Models\ModelDocument;
use App\Services\ChampsSaisieService;
use App\Services\DocumentGenerationService;
use App\Services\TemplateRendererService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDossiers;
use Tests\TestCase;

class ChampsSaisieTest extends TestCase
{
    use CreatesDossiers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTypes();
        $this->travelTo('2026-03-01 10:00:00');
    }

    private function documentAvecSaisies(?array $saisies)
    {
        $document = $this->document();
        $modele = ModelDocument::where('type_document_id', $document->type_document_id)->firstOrFail();
        $modele->update(['contenu' => '<p>Brut {{SALAIRE_BRUT}} / Net {{MONTANT_SAISIE_MANUELLE_4}} / {{ANCIENNETE}}</p>']);
        $document->update(['model_document_id' => $modele->id, 'donnees_saisies' => $saisies]);

        return $document->fresh();
    }

    public function test_only_fields_used_by_the_template_are_required(): void
    {
        $service = app(ChampsSaisieService::class);

        $this->assertSame(
            ['SALAIRE_BRUT', 'DATE_RETOUR'],
            $service->requis('{{ NOM_COMPLET }} {{SALAIRE_BRUT}} {{DATE_RETOUR}} {{SALAIRE_BRUT}}')
        );
        $this->assertSame([], $service->requis('<p>{{ NOM_COMPLET }}</p>'));
    }

    public function test_submission_is_blocked_while_a_manual_field_is_missing(): void
    {
        $document = $this->documentAvecSaisies(['SALAIRE_BRUT' => 150000]);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('Net à payer');

        app(WorkflowService::class)->soumettre($document, $this->acteur(Role::RhJunior));
    }

    public function test_empty_manual_fields_are_reported_as_missing(): void
    {
        $document = $this->documentAvecSaisies(['SALAIRE_BRUT' => '']);

        $this->assertSame(['SALAIRE_BRUT', 'MONTANT_SAISIE_MANUELLE_4'], app(ChampsSaisieService::class)->manquants($document));
    }

    public function test_values_are_formatted_and_escaped_in_the_rendered_document(): void
    {
        $document = $this->documentAvecSaisies([
            'SALAIRE_BRUT' => 150000,
            'MONTANT_SAISIE_MANUELLE_4' => '<b>12</b>',
        ]);

        $rendu = app(TemplateRendererService::class)->rendre($document->modele->contenu, $document);

        $this->assertStringContainsString('Brut 150 000', $rendu);
        $this->assertStringContainsString('&lt;b&gt;12&lt;/b&gt;', $rendu);
        $this->assertSame([], app(ChampsSaisieService::class)->manquants($document));
        $this->assertSame([], app(TemplateRendererService::class)->variablesInconnues($document->modele->contenu));
    }

    public function test_generation_service_rejects_missing_fields_before_freezing_content(): void
    {
        $document = $this->documentAvecSaisies(null);

        $this->expectException(\Throwable::class);
        try {
            app(DocumentGenerationService::class)->finaliser($document);
        } finally {
            $this->assertNull($document->fresh()->contenu_genere);
        }
    }
}
