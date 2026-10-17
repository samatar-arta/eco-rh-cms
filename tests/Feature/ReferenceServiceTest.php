<?php

namespace Tests\Feature;

use App\Enums\RhModule;
use App\Enums\Role;
use App\Models\ReferenceSequence;
use App\Services\ReferenceService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesDossiers;
use Tests\TestCase;

class ReferenceServiceTest extends TestCase
{
    use CreatesDossiers, RefreshDatabase;

    private ReferenceService $references;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTypes();
        $this->travelTo('2026-03-01 10:00:00');
        $this->references = app(ReferenceService::class);
    }

    public function test_all_document_types_share_one_counter(): void
    {
        $attestation = $this->document('ATTESTATION_TRAVAIL');
        $certificat = $this->document('CERTIFICAT_TRAVAIL');
        $salaire = $this->document('ATTESTATION_SALAIRE');

        $this->assertSame('0001/DA_DARH/DLS/2026', $this->references->assign($attestation));
        $this->assertSame('0002/DA_DARH/DLS/2026', $this->references->assign($certificat));
        $this->assertSame('0003/DA_DARH/DLS/2026', $this->references->assign($salaire));
    }

    public function test_each_module_has_its_own_counter(): void
    {
        $this->references->assign($this->document());

        $this->assertSame('0001/DS_DARH/DLS/2026', $this->references->assign($this->sanction()));
        $this->assertSame('0001/PR_DARH/DLS/2026', $this->references->assign($this->promotion()));
        $this->assertSame('0002/DA_DARH/DLS/2026', $this->references->assign($this->document('CERTIFICAT_TRAVAIL')));
    }

    public function test_counter_restarts_every_year_and_keeps_the_configured_prefix(): void
    {
        $this->references->next(RhModule::Document);
        ReferenceSequence::where('module', 'DOCUMENT')->update(['prefixe' => 'DA_DARH/DLS-X']);

        $this->travelTo('2027-01-04 09:00:00');

        $this->assertSame('0001/DA_DARH/DLS-X/2027', $this->references->next(RhModule::Document));
        $this->assertSame(2, ReferenceSequence::where('module', 'DOCUMENT')->count());
    }

    public function test_assign_is_idempotent_and_does_not_consume_a_number(): void
    {
        $document = $this->document();

        $premiere = $this->references->assign($document);
        $reprise = $this->references->assign($document->fresh());

        $this->assertSame($premiere, $reprise);
        $this->assertSame(1, ReferenceSequence::where('module', 'DOCUMENT')->value('dernier_numero'));
    }

    public function test_reference_is_immutable_once_assigned(): void
    {
        $document = $this->document();
        $this->references->assign($document);

        $this->expectException(LogicException::class);
        $document->fresh()->update(['reference' => '9999/DA_DARH/DLS/2026']);
    }

    public function test_assigned_record_cannot_be_deleted_and_its_number_is_never_reused(): void
    {
        $premier = $this->document();
        $this->references->assign($premier);

        try {
            $premier->fresh()->delete();
            $this->fail('La suppression aurait dû être refusée.');
        } catch (LogicException) {
            // attendu
        }

        $this->assertSame('0002/DA_DARH/DLS/2026', $this->references->assign($this->document()));
    }

    public function test_draft_and_rejected_dossiers_consume_no_reference(): void
    {
        $workflow = app(WorkflowService::class);
        $junior = $this->acteur(Role::RhJunior);
        $senior = $this->acteur(Role::RhSenior);

        $brouillon = $this->document();
        $workflow->ouvrir($brouillon, $junior);

        $rejete = $this->document('CERTIFICAT_TRAVAIL');
        $workflow->soumettre($rejete, $junior);
        $workflow->rejeter($rejete, $senior, 'Pièces manquantes');

        $this->assertNull($brouillon->fresh()->reference);
        $this->assertNull($rejete->fresh()->reference);
        $this->assertSame(0, (int) ReferenceSequence::where('module', 'DOCUMENT')->value('dernier_numero'));
    }
}
