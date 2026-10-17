<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Document;
use App\Models\Employe;
use App\Models\Promotion;
use App\Models\Sanction;
use App\Models\SiteDepartement;
use App\Models\TypeDocument;
use App\Models\TypePromotion;
use App\Models\TypeSanction;
use App\Models\User;
use Database\Seeders\TypesEtModelesSeeder;

trait CreatesDossiers
{
    protected SiteDepartement $perimetre;

    protected Employe $employe;

    protected function seedTypes(): void
    {
        $this->seed(TypesEtModelesSeeder::class);
        $this->perimetre = SiteDepartement::factory()->create();
        $this->employe = Employe::factory()->create([
            'societe_id' => $this->perimetre->societe_id,
            'departement_id' => $this->perimetre->departement_id,
            'site_id' => $this->perimetre->site_id,
        ]);
    }

    /** Utilisateur actif, avec le rôle, rattaché au périmètre de l'employé de test. */
    protected function acteur(Role $role, bool $dansLePerimetre = true): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        if ($dansLePerimetre) {
            $user->siteDepartements()->attach($this->perimetre);
        }

        return $user->fresh();
    }

    protected function document(string $typeCode = 'ATTESTATION_TRAVAIL'): Document
    {
        return Document::create([
            'matricule' => $this->employe->matricule,
            'type_document_id' => TypeDocument::where('code', $typeCode)->firstOrFail()->id,
        ]);
    }

    protected function sanction(): Sanction
    {
        return Sanction::create([
            'matricule' => $this->employe->matricule,
            'type_sanction_id' => TypeSanction::firstOrFail()->id,
            'date_sanction' => '2026-03-02',
        ]);
    }

    protected function promotion(): Promotion
    {
        return Promotion::create([
            'matricule' => $this->employe->matricule,
            'type_promotion_id' => TypePromotion::firstOrFail()->id,
            'nouveau_grade' => 'G2',
            'date_effet' => '2026-04-01',
        ]);
    }
}
