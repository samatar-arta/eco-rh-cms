<?php

namespace Tests\Feature;

use App\Enums\DistributionCanal;
use App\Enums\DistributionStatut;
use App\Enums\PaylistImportStatut;
use App\Enums\PaylistStatut;
use App\Enums\Role;
use App\Exceptions\PaylistException;
use App\Filament\Resources\PaylistDistributions\Pages\ListPaylistDistributions;
use App\Filament\Resources\PaylistImports\Pages\ViewPaylistImport;
use App\Filament\Resources\Paylists\Pages\ListPaylists;
use App\Mail\BulletinPaieMail;
use App\Models\Contact;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\Paylist;
use App\Models\PaylistDistribution;
use App\Models\PaylistImport;
use App\Models\User;
use App\Services\PaylistDistributionService;
use App\Services\PaylistImportService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesDossiers;
use Tests\TestCase;
use ZipArchive;

class PaylistTest extends TestCase
{
    use CreatesDossiers, RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTypes();
    }

    private function gestionnaire(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::GestionnairePaie);

        return $u->fresh();
    }

    /** @param array<string,string> $fichiers nom => contenu */
    private function zip(array $fichiers, string $nom = 'sage.zip'): string
    {
        $chemin = 'paylist-uploads/'.$nom;
        Storage::disk('local')->put($chemin, '');
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($chemin), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($fichiers as $n => $c) {
            $zip->addFromString($n, $c);
        }
        $zip->close();

        return $chemin;
    }

    private function importer(User $gp, array $fichiers, int $mois = 2, int $annee = 2026): PaylistImport
    {
        $service = app(PaylistImportService::class);
        $import = $service->analyser($gp, $this->zip($fichiers), 'sage.zip', $mois, $annee);

        return $service->confirmer($gp, $import);
    }

    public function test_valid_archive_is_imported_with_preview_first(): void
    {
        $gp = $this->gestionnaire();
        $m = $this->employe->matricule;

        $import = app(PaylistImportService::class)->analyser($gp, $this->zip(["{$m}.pdf" => self::PDF]), 'sage.zip', 2, 2026);

        $this->assertSame(PaylistImportStatut::Analyse, $import->statut_import);
        $this->assertSame('OK', $import->apercu[0]['statut']);
        $this->assertSame(0, Paylist::count(), "L'analyse n'importe rien.");

        $import = app(PaylistImportService::class)->confirmer($gp, $import);

        $this->assertSame(PaylistImportStatut::Importe, $import->statut_import);
        $this->assertSame(1, $import->lignes_importees);
        $paylist = Paylist::firstOrFail();
        $this->assertSame($m, $paylist->matricule);
        $this->assertSame(hash('sha256', self::PDF), $paylist->empreinte_fichier);
        Storage::disk('local')->assertExists($paylist->fichier_path);
    }

    public function test_invalid_lines_do_not_block_valid_ones_and_are_reported(): void
    {
        $gp = $this->gestionnaire();
        $m = $this->employe->matricule;

        $import = $this->importer($gp, [
            "{$m}_fevrier.pdf" => self::PDF,
            'INCONNU99.pdf' => self::PDF,
            "{$m}_bis.pdf" => self::PDF,
            'faux.pdf' => 'pas un pdf',
            'notes.txt' => 'x',
        ]);

        $this->assertSame(PaylistImportStatut::ImporteAvecErreurs, $import->statut_import);
        $this->assertSame(1, $import->lignes_importees);
        $this->assertSame(4, $import->lignes_rejetees);
        $codes = collect($import->rapport_erreurs)->pluck('statut')->all();
        $this->assertContains('MATRICULE_INCONNU', $codes);
        $this->assertContains('DOUBLON_ARCHIVE', $codes);
        $this->assertContains('NOM_INVALIDE', $codes);
        $this->assertSame(1, Paylist::count());
    }

    public function test_same_period_cannot_be_imported_twice(): void
    {
        $gp = $this->gestionnaire();
        $m = $this->employe->matricule;

        $this->importer($gp, ["{$m}.pdf" => self::PDF]);
        $second = $this->importer($gp, ["{$m}.pdf" => self::PDF]);

        $this->assertSame(PaylistImportStatut::Echec, $second->statut_import);
        $this->assertSame('DOUBLON_EXISTANT', $second->rapport_erreurs[0]['statut']);
        $this->assertSame(1, Paylist::count());

        // Autre période : accepté.
        $this->assertSame(PaylistImportStatut::Importe, $this->importer($gp, ["{$m}.pdf" => self::PDF], 3)->statut_import);
    }

    public function test_confirmation_is_idempotent_and_resumable(): void
    {
        $gp = $this->gestionnaire();
        $m = $this->employe->matricule;
        $service = app(PaylistImportService::class);

        $import = $service->confirmer($gp, $service->analyser($gp, $this->zip(["{$m}.pdf" => self::PDF]), 'a.zip', 2, 2026));
        $service->confirmer($gp, $import);

        $this->assertSame(1, Paylist::count());
        $this->assertSame(PaylistImportStatut::Importe, $import->refresh()->statut_import);
    }

    public function test_invalid_file_is_rejected_at_analysis(): void
    {
        $gp = $this->gestionnaire();
        Storage::disk('local')->put('paylist-uploads/x.zip', 'ceci n\'est pas une archive');

        $this->expectException(PaylistException::class);
        app(PaylistImportService::class)->analyser($gp, 'paylist-uploads/x.zip', 'x.zip', 2, 2026);
    }

    public function test_only_the_payroll_manager_can_import(): void
    {
        $senior = $this->acteur(Role::RhSenior);
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);

        foreach ([$senior, $admin->fresh()] as $user) {
            try {
                app(PaylistImportService::class)->analyser($user, $this->zip([]), 'a.zip', 2, 2026);
                $this->fail('Import autorisé à tort.');
            } catch (PaylistException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_policies_restrict_paylist_access_to_the_payroll_manager(): void
    {
        $gp = $this->gestionnaire();
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);
        $senior = $this->acteur(Role::RhManager);
        $paylist = $this->bulletin($gp);

        $this->assertTrue($gp->can('view', $paylist));
        $this->assertTrue($gp->can('create', PaylistImport::class));
        $this->assertFalse($gp->can('delete', $paylist));
        $this->assertFalse($admin->fresh()->can('view', $paylist));
        $this->assertFalse($senior->can('view', $paylist));
        $this->assertFalse($senior->can('viewAny', PaylistDistribution::class));
    }

    private function bulletin(User $gp, ?Employe $employe = null): Paylist
    {
        $employe ??= $this->employe;
        $this->importer($gp, ["{$employe->matricule}.pdf" => self::PDF]);

        return Paylist::where('matricule', $employe->matricule)->firstOrFail();
    }

    public function test_download_route_is_authenticated_and_authorized(): void
    {
        $gp = $this->gestionnaire();
        $paylist = $this->bulletin($gp);
        $senior = $this->acteur(Role::RhSenior);

        $this->get(route('paylists.fichier', $paylist))->assertRedirect();
        $this->actingAs($senior)->get(route('paylists.fichier', $paylist))->assertForbidden();
        $this->actingAs($gp)->get(route('paylists.fichier', $paylist))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_distribution_by_email_to_the_right_contact(): void
    {
        Mail::fake();
        $gp = $this->gestionnaire();
        $paylist = $this->bulletin($gp);
        $contact = Contact::factory()->create([
            'societe_id' => $this->employe->societe_id, 'departement_id' => $this->employe->departement_id,
        ]);

        [$d] = app(PaylistDistributionService::class)->distribuer($gp, [$paylist->id], $contact, DistributionCanal::Email);

        $this->assertSame(DistributionStatut::Envoye, $d->statut);
        $this->assertNotNull($d->date_distribution);
        $this->assertSame(PaylistStatut::Distribue, $paylist->refresh()->statut);
        Mail::assertSent(BulletinPaieMail::class, fn ($m) => $m->hasTo($contact->email));
    }

    public function test_distribution_is_idempotent(): void
    {
        Mail::fake();
        $gp = $this->gestionnaire();
        $paylist = $this->bulletin($gp);
        $contact = Contact::factory()->create(['societe_id' => $this->employe->societe_id]);
        $service = app(PaylistDistributionService::class);

        $service->distribuer($gp, [$paylist->id], $contact, DistributionCanal::Email);
        $service->distribuer($gp, [$paylist->id], $contact, DistributionCanal::Email);

        $this->assertSame(1, PaylistDistribution::count());
        Mail::assertSent(BulletinPaieMail::class, 1);
    }

    public function test_unauthorized_recipient_is_blocked_and_nothing_is_created(): void
    {
        Mail::fake();
        $gp = $this->gestionnaire();
        $paylist = $this->bulletin($gp);
        $service = app(PaylistDistributionService::class);

        $autreSociete = Contact::factory()->create();
        $autreDepartement = Contact::factory()->create([
            'societe_id' => $this->employe->societe_id,
            'departement_id' => Departement::factory()->create(['societe_id' => $this->employe->societe_id])->id,
        ]);
        $inactif = Contact::factory()->create(['societe_id' => $this->employe->societe_id, 'is_active' => false]);
        $sansEmail = Contact::factory()->create(['societe_id' => $this->employe->societe_id, 'email' => null]);

        foreach ([$autreSociete, $autreDepartement, $inactif, $sansEmail] as $contact) {
            try {
                $service->distribuer($gp, [$paylist->id], $contact, DistributionCanal::Email);
                $this->fail('Destinataire non autorisé accepté.');
            } catch (PaylistException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, PaylistDistribution::count());
        Mail::assertNothingSent();
    }

    public function test_a_batch_with_one_forbidden_bulletin_is_refused_entirely(): void
    {
        Mail::fake();
        $gp = $this->gestionnaire();
        $ok = $this->bulletin($gp);
        $autre = Employe::factory()->create(); // autre société
        $ko = $this->bulletin($gp, $autre);
        $contact = Contact::factory()->create(['societe_id' => $this->employe->societe_id]);

        $this->expectException(PaylistException::class);

        try {
            app(PaylistDistributionService::class)->distribuer($gp, [$ok->id, $ko->id], $contact, DistributionCanal::Email);
        } finally {
            $this->assertSame(0, PaylistDistribution::count());
        }
    }

    public function test_failed_send_is_logged_and_retry_succeeds(): void
    {
        $gp = $this->gestionnaire();
        $paylist = $this->bulletin($gp);
        $contact = Contact::factory()->create(['societe_id' => $this->employe->societe_id]);
        $service = app(PaylistDistributionService::class);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP indisponible'));
        [$d] = $service->distribuer($gp, [$paylist->id], $contact, DistributionCanal::Email);

        $this->assertSame(DistributionStatut::Echec, $d->statut);
        $this->assertStringContainsString('SMTP indisponible', $d->commentaire);
        $this->assertSame(1, $d->tentatives);

        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $d = $service->relancer($gp, $d);

        $this->assertSame(DistributionStatut::Envoye, $d->statut);
        $this->assertSame(2, $d->tentatives);
        $this->assertSame(1, PaylistDistribution::count());
    }

    public function test_hand_delivery_requires_a_proof(): void
    {
        $gp = $this->gestionnaire();
        $paylist = $this->bulletin($gp);
        $contact = Contact::factory()->create(['societe_id' => $this->employe->societe_id]);
        $service = app(PaylistDistributionService::class);

        try {
            $service->distribuer($gp, [$paylist->id], $contact, DistributionCanal::Remise);
            $this->fail('Preuve exigée.');
        } catch (PaylistException) {
            $this->assertSame(0, PaylistDistribution::count());
        }

        [$d] = $service->distribuer($gp, [$paylist->id], $contact, DistributionCanal::Remise, 'BORD-2026-001');
        $this->assertSame(DistributionStatut::Envoye, $d->statut);
        $this->assertSame('BORD-2026-001', $d->preuve_distribution);
    }

    public function test_non_payroll_user_cannot_distribute_and_contact_has_no_account(): void
    {
        $gp = $this->gestionnaire();
        $paylist = $this->bulletin($gp);
        $contact = Contact::factory()->create(['societe_id' => $this->employe->societe_id]);

        $this->expectException(PaylistException::class);
        try {
            app(PaylistDistributionService::class)->distribuer($this->acteur(Role::RhManager), [$paylist->id], $contact, DistributionCanal::Email);
        } finally {
            $this->assertNull(User::where('email', $contact->email)->first());
        }
    }

    public function test_filament_screens_are_reserved_to_the_payroll_manager(): void
    {
        Filament::setCurrentPanel('rh');
        $gp = $this->gestionnaire();
        $this->bulletin($gp);

        Livewire::actingAs($gp)->test(ListPaylists::class)->assertSuccessful();
        Livewire::actingAs($gp)->test(ListPaylistDistributions::class)->assertSuccessful();

        foreach ([ListPaylists::class, ListPaylistDistributions::class] as $page) {
            $this->actingAs($this->acteur(Role::RhManager))->get($page::getUrl())->assertForbidden();
        }
    }

    public function test_import_page_confirms_and_bulk_distributes(): void
    {
        Filament::setCurrentPanel('rh');
        Mail::fake();
        $gp = $this->gestionnaire();
        $m = $this->employe->matricule;
        $import = app(PaylistImportService::class)->analyser($gp, $this->zip(["{$m}.pdf" => self::PDF]), 'sage.zip', 2, 2026);

        Livewire::actingAs($gp)->test(ViewPaylistImport::class, ['record' => $import->id])
            ->callAction('confirmer')->assertNotified();

        $this->assertSame(PaylistImportStatut::Importe, $import->refresh()->statut_import);
        $paylist = Paylist::firstOrFail();
        $contact = Contact::factory()->create(['societe_id' => $this->employe->societe_id]);

        Livewire::actingAs($gp)->test(ListPaylists::class)
            ->selectTableRecords([$paylist->id])
            ->callAction(TestAction::make('distribuer')->table()->bulk(), [
                'societe_id' => $this->employe->societe_id, 'contact_id' => $contact->id, 'canal' => 'EMAIL',
            ])->assertHasNoActionErrors();

        $this->assertSame(DistributionStatut::Envoye, PaylistDistribution::firstOrFail()->statut);
    }
}
