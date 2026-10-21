<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SwappedSocietesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_22_000200_fix_swapped_sgtd_dmp_societes.php');
    }

    private function societe(string $code, string $name): int
    {
        return DB::table('societes')->insertGetId(['code' => $code, 'name' => $name, 'is_active' => true]);
    }

    public function test_swapped_sgtd_and_dmp_are_exchanged_with_their_departments_untouched(): void
    {
        // Inversé : la société codée SGTD (id a) possède le département DMP.
        $a = $this->societe('SGTD', 'SOCIETE DE GESTION TERMINAL DE DJIBOUTI SA');
        $b = $this->societe('DMP', 'DJIBOUTI MULTI-PROPOSE DORALEH SA');
        DB::table('departements')->insert(['name' => 'DEPT SUPPORT&INFRASTRUCTURE', 'abrege' => 'DSI', 'societe_id' => $a, 'is_active' => true]);
        DB::table('departements')->insert(['name' => 'RESSOURCE HUMAINE', 'abrege' => 'RH', 'societe_id' => $b, 'is_active' => true]);

        $this->migration()->up();

        $this->assertSame('DMP', DB::table('societes')->where('id', $a)->value('code'));
        $this->assertSame('DJIBOUTI MULTI-PROPOSE DORALEH SA', DB::table('societes')->where('id', $a)->value('name'));
        $this->assertSame('SGTD', DB::table('societes')->where('id', $b)->value('code'));
        $this->assertSame($a, (int) DB::table('departements')->where('name', 'DEPT SUPPORT&INFRASTRUCTURE')->value('societe_id'));
    }

    public function test_it_is_a_no_op_when_already_correct_and_when_run_twice(): void
    {
        $a = $this->societe('DMP', 'DJIBOUTI MULTI-PROPOSE DORALEH SA');
        $b = $this->societe('SGTD', 'SOCIETE DE GESTION TERMINAL DE DJIBOUTI SA');
        DB::table('departements')->insert(['name' => 'DEPT SUPPORT&INFRASTRUCTURE', 'abrege' => 'DSI', 'societe_id' => $a, 'is_active' => true]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('DMP', DB::table('societes')->where('id', $a)->value('code'));
        $this->assertSame('SGTD', DB::table('societes')->where('id', $b)->value('code'));
    }

    public function test_it_does_nothing_on_an_empty_database(): void
    {
        $this->migration()->up();

        $this->assertSame(0, DB::table('societes')->count());
    }
}
