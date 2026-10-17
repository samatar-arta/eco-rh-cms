<?php

namespace Tests\Feature;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Vraie concurrence : plusieurs processus PHP réclament une référence en même
 * temps sur MySQL/InnoDB (SQLite ne verrouille pas les lignes). Ignoré si la
 * base `eco_rh_dls_test` n'existe pas. Créer : CREATE DATABASE eco_rh_dls_test;
 */
#[Group('mysql')]
class ReferenceConcurrencyTest extends TestCase
{
    private const BASE = 'eco_rh_dls_test';

    /** @var array<string, string> */
    private array $env;

    protected function setUp(): void
    {
        parent::setUp();

        $c = config('database.connections.mysql');
        $this->env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $c['host'],
            'DB_PORT' => (string) $c['port'],
            'DB_DATABASE' => self::BASE,
            'DB_USERNAME' => $c['username'],
            'DB_PASSWORD' => (string) $c['password'],
            'DB_URL' => '',
        ];

        try {
            new PDO("mysql:host={$c['host']};port={$c['port']};dbname=".self::BASE, $c['username'], (string) $c['password']);
        } catch (PDOException) {
            $this->markTestSkipped('Base MySQL '.self::BASE.' indisponible.');
        }
    }

    private function processus(array $args): Process
    {
        return new Process([PHP_BINARY, 'artisan', ...$args], base_path(), $this->env);
    }

    public function test_parallel_processes_never_receive_the_same_reference(): void
    {
        $this->assertSame(self::BASE, $this->env['DB_DATABASE']);

        $reset = $this->processus(['migrate:fresh', '--force']);
        $reset->setTimeout(300)->mustRun();

        $annee = date('Y');
        $references = [];

        // Tour 1 : aucune séquence n'existe (course à la création) ; tour 2 : séquence existante.
        foreach ([1, 2] as $tour) {
            $processus = [];
            for ($i = 0; $i < 8; $i++) {
                $p = $this->processus(['references:next', 'DOCUMENT'])->setTimeout(120);
                $p->start();
                $processus[] = $p;
            }
            foreach ($processus as $p) {
                $p->wait();
                $this->assertTrue($p->isSuccessful(), "Tour $tour : ".$p->getErrorOutput().$p->getOutput());
                $references[] = trim($p->getOutput());
            }
        }

        $this->assertCount(16, array_unique($references), 'Doublon de référence détecté.');

        $attendues = array_map(fn ($n) => sprintf('%04d/DA_DARH/DLS/%s', $n, $annee), range(1, 16));
        sort($references);
        $this->assertSame($attendues, $references);
    }
}
