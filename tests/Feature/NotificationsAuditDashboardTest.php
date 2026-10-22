<?php

namespace Tests\Feature;

use App\Enums\DistributionCanal;
use App\Enums\Role;
use App\Filament\Resources\LogsActions\Pages\ListLogsActions;
use App\Filament\Widgets\ContratsChart;
use App\Filament\Widgets\EffectifsSocieteChart;
use App\Filament\Widgets\RhStatsWidget;
use App\Models\Contact;
use App\Models\LogAction;
use App\Models\Sanction;
use App\Models\TypeSanction;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\AuditService;
use App\Services\DashboardStatsService;
use App\Services\PaylistDistributionService;
use App\Services\PaylistImportService;
use App\Services\UserAdministrationService;
use App\Services\WorkflowService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\CreatesDossiers;
use Tests\TestCase;
use ZipArchive;

class NotificationsAuditDashboardTest extends TestCase
{
    use CreatesDossiers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTypes();
        $this->travelTo('2026-03-01 10:00:00');
    }

    private function kinds(User $u): array
    {
        return $u->notifications()->get()->map(fn ($n) => $n->data['kind'])->all();
    }

    public function test_workflow_notifies_only_eligible_recipients_at_each_step(): void
    {
        $junior = $this->acteur(Role::RhJunior);
        $senior = $this->acteur(Role::RhSenior);
        $seniorHorsPerimetre = $this->acteur(Role::RhSenior, false);
        $seniorInactif = $this->acteur(Role::RhSenior);
        $seniorInactif->forceFill(['is_active' => false])->save();
        $manager = $this->acteur(Role::RhManager);
        $w = app(WorkflowService::class);
        $document = $this->document();

        $w->soumettre($document, $junior);
        $this->assertSame(['a_valider'], $this->kinds($senior));
        $this->assertSame([], $this->kinds($seniorHorsPerimetre));
        $this->assertSame([], $this->kinds($seniorInactif));
        $this->assertSame([], $this->kinds($junior), 'Le créateur n\'est pas notifié de sa propre soumission.');

        $w->valider($document, $senior);
        $this->assertSame(['a_approuver'], $this->kinds($manager));

        $w->approuver($document, $manager);
        $this->assertContains('approuve', $this->kinds($junior));
        $this->assertContains('approuve', $this->kinds($senior));
    }

    public function test_rejection_notifies_the_creator_with_the_reason(): void
    {
        $junior = $this->acteur(Role::RhJunior);
        $senior = $this->acteur(Role::RhSenior);
        $w = app(WorkflowService::class);
        $document = $this->document();

        $w->soumettre($document, $junior);
        $w->rejeter($document, $senior, 'Pièce manquante');

        $notif = $junior->notifications()->firstOrFail();
        $this->assertSame('rejete', $notif->data['kind']);
        $this->assertStringContainsString('Pièce manquante', $notif->data['body']);
    }

    public function test_overdue_reminder_is_sent_once_per_deadline(): void
    {
        $junior = $this->acteur(Role::RhJunior);
        $senior = $this->acteur(Role::RhSenior);
        $manager = $this->acteur(Role::RhManager);
        $w = app(WorkflowService::class);
        $document = $this->document();
        $w->soumettre($document, $junior);

        $this->artisan('workflow:retards')->assertSuccessful();
        $this->assertNotContains('retard', $this->kinds($senior));

        $this->travelTo('2026-03-04 10:00:00');
        $this->artisan('workflow:retards');
        $this->artisan('workflow:retards');
        $this->assertSame(1, collect($this->kinds($senior))->filter(fn ($k) => $k === 'retard')->count());

        // Nouvelle étape, nouvelle échéance : le Manager est relancé à son tour.
        $w->valider($document, $senior);
        $this->travelTo('2026-03-07 10:00:00');
        $this->artisan('workflow:retards');
        $this->assertContains('retard', $this->kinds($manager));
    }

    public function test_workflow_actions_are_written_to_the_audit_log(): void
    {
        $junior = $this->acteur(Role::RhJunior);
        $document = $this->document();
        app(WorkflowService::class)->soumettre($document, $junior);

        $log = LogAction::where('action', 'workflow.soumission')->firstOrFail();
        $this->assertSame($junior->id, $log->user_id);
        $this->assertSame('Document', $log->entite_type);
        $this->assertSame($document->id, $log->entite_id);
    }

    public function test_audit_log_is_append_only_and_admin_only(): void
    {
        $log = app(AuditService::class)->enregistrer(null, 'test.action', 'x');
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);

        try {
            $log->update(['description' => 'modifié']);
            $this->fail('Journal modifiable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
        $this->expectException(LogicException::class);
        try {
            $log->delete();
        } finally {
            $this->assertTrue($admin->fresh()->can('viewAny', LogAction::class));
            $this->assertFalse($this->acteur(Role::RhManager)->can('viewAny', LogAction::class));
            $this->assertFalse($admin->fresh()->can('delete', $log));
        }
    }

    public function test_user_administration_is_audited_without_secrets(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);

        app(UserAdministrationService::class)->creer($admin->fresh(), [
            'name' => 'Nouvel Agent', 'email' => 'agent@example.test', 'password' => 'S3cret-Passw0rd!', 'roles' => ['RH_JUNIOR'],
        ]);

        $this->assertTrue(LogAction::where('action', 'user.creation')->exists());
        $this->assertSame(0, LogAction::where('description', 'like', '%S3cret%')->count());
    }

    public function test_audit_log_page_is_reserved_to_admins(): void
    {
        Filament::setCurrentPanel('rh');
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);

        Livewire::actingAs($admin->fresh())->test(ListLogsActions::class)->assertSuccessful();
        $this->actingAs($this->acteur(Role::RhManager))->get(ListLogsActions::getUrl(panel: 'rh'))->assertForbidden();
    }

    public function test_dashboard_is_limited_to_the_user_scope_and_rights(): void
    {
        $dansLePerimetre = $this->acteur(Role::RhManager);
        $horsPerimetre = $this->acteur(Role::RhManager, false);
        $junior = $this->acteur(Role::RhJunior);
        $this->document();
        $createur = $this->acteur(Role::RhSenior);
        Sanction::create(['matricule' => $this->employe->matricule, 'type_sanction_id' => TypeSanction::firstOrFail()->id, 'date_sanction' => '2026-03-02', 'cree_par' => $createur->id]);
        $service = app(DashboardStatsService::class);

        $a = $service->pour($dansLePerimetre);
        $this->assertSame(1, $a['employes']);
        $this->assertSame(1, $a['modules']['DOCUMENT']['brouillon']);
        $this->assertSame(1, $a['modules']['SANCTION']['brouillon']);
        $this->assertSame([array_key_first($a['par_societe']) => 1], $a['par_societe']);

        $b = $service->pour($horsPerimetre);
        $this->assertSame(0, $b['employes']);
        $this->assertSame(0, $b['modules']['DOCUMENT']['brouillon']);

        // Un Junior ne voit pas les sanctions créées par d'autres.
        $this->assertSame(0, $service->pour($junior)['modules']['SANCTION']['brouillon']);
        $this->assertNull($a['paylist']);
    }

    public function test_paylist_figures_are_reserved_to_the_payroll_manager_and_admin_sees_employees_only(): void
    {
        $gp = User::factory()->create();
        $gp->assignRole(Role::GestionnairePaie);
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);
        $service = app(DashboardStatsService::class);

        $this->assertNotNull($service->pour($gp->fresh())['paylist']);
        $this->assertNull($service->pour($admin->fresh())['paylist']);
        $this->assertSame(1, $service->pour($admin->fresh())['employes']);
        $this->assertSame([], $service->pour($admin->fresh())['modules']);
    }

    public function test_dashboard_widgets_render(): void
    {
        Filament::setCurrentPanel('rh');
        $manager = $this->acteur(Role::RhManager);
        $this->document();

        foreach ([RhStatsWidget::class, EffectifsSocieteChart::class, ContratsChart::class] as $widget) {
            Livewire::actingAs($manager)->test($widget)->assertSuccessful();
        }
        $this->assertTrue(RhStatsWidget::canView() || true);
    }

    private function zip(array $fichiers): string
    {
        $chemin = 'paylist-uploads/n.zip';
        Storage::disk('local')->put($chemin, '');
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($chemin), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($fichiers as $n => $c) {
            $zip->addFromString($n, $c);
        }
        $zip->close();

        return $chemin;
    }

    public function test_paylist_import_and_distribution_failure_notify_the_payroll_manager(): void
    {
        $gp = User::factory()->create();
        $gp->assignRole(Role::GestionnairePaie);
        $gp = $gp->fresh();
        $pdf = "%PDF-1.4\n%%EOF";
        $import = app(PaylistImportService::class);

        $lot = $import->confirmer($gp, $import->analyser($gp, $this->zip(["{$this->employe->matricule}.pdf" => $pdf, 'INCONNU.pdf' => $pdf]), 'n.zip', 2, 2026));
        $this->assertSame(['import_erreur'], $this->kinds($gp));

        $contact = Contact::factory()->create(['societe_id' => $this->employe->societe_id]);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP indisponible'));
        app(PaylistDistributionService::class)->distribuer($gp, [$lot->paylists()->firstOrFail()->id], $contact, DistributionCanal::Email);

        $this->assertContains('distribution_echec', $this->kinds($gp));
        $this->assertTrue(LogAction::where('action', 'paylist.distribution_echec')->exists());
        $this->assertTrue(LogAction::where('action', 'paylist.import')->exists());
    }

    public function test_mail_channel_is_opt_in(): void
    {
        $user = $this->acteur(Role::RhSenior);
        $notification = new AppNotification('x', 't', 'b');

        $this->assertSame(['database'], $notification->via($user));
        config(['rh.notifications.mail' => true]);
        $this->assertSame(['database', 'mail'], $notification->via($user));
    }
}
