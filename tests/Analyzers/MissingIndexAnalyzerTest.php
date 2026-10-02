<?php

namespace Sartajgit\QueryXray\Tests\Analyzers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Sartajgit\QueryXray\Analyzers\MissingIndexAnalyzer;
use Sartajgit\QueryXray\Support\QueryFingerprint;
use Sartajgit\QueryXray\Tests\TestCase;

class MissingIndexAnalyzerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('xray_test_people', function ($table) {
            $table->id();
            $table->string('email');
            $table->unsignedInteger('age');
        });

        for ($i = 1; $i <= 200; $i++) {
            DB::table('xray_test_people')->insert([
                'email' => "person{$i}@example.com",
                'age' => 20 + ($i % 50),
            ]);
        }
    }

    protected function makeQuery(string $sql, array $bindings = []): array
    {
        return [
            'sql' => $sql,
            'bindings' => $bindings,
            'file' => 'app/Repositories/PersonRepository.php',
            'line' => 42,
            'fingerprint' => QueryFingerprint::make($sql),
        ];
    }

    public function test_non_select_queries_are_skipped_without_error(): void
    {
        $analyzer = new MissingIndexAnalyzer(10);

        $findings = $analyzer->analyze([
            $this->makeQuery('update `xray_test_people` set `age` = ? where `id` = ?', [30, 1]),
            $this->makeQuery('delete from `xray_test_people` where `id` = ?', [1]),
        ]);

        $this->assertCount(0, $findings);
    }

    public function test_does_not_crash_on_a_real_select_against_an_unindexed_column(): void
    {
        $analyzer = new MissingIndexAnalyzer(10);

        $findings = $analyzer->analyze([
            $this->makeQuery('select * from `xray_test_people` where `email` = ?', ['person1@example.com']),
        ]);

        $this->assertIsArray($findings);
    }

    public function test_duplicate_fingerprints_are_only_explained_once(): void
    {
        $analyzer = new MissingIndexAnalyzer(10);

        $findings = $analyzer->analyze([
            $this->makeQuery('select * from `xray_test_people` where `age` = ?', [25]),
            $this->makeQuery('select * from `xray_test_people` where `age` = ?', [30]),
            $this->makeQuery('select * from `xray_test_people` where `age` = ?', [35]),
        ]);

        $this->assertLessThanOrEqual(1, count($findings));
    }

    public function test_broken_sql_does_not_throw_an_exception(): void
    {
        $analyzer = new MissingIndexAnalyzer(10);

        $findings = $analyzer->analyze([
            $this->makeQuery('select * from `table_that_does_not_exist` where `x` = ?', [1]),
        ]);

        $this->assertCount(0, $findings, 'A failed EXPLAIN must fail safe (empty result), never throw.');
    }

    public function test_findings_include_time_and_connection_for_persistence(): void
    {
        $analyzer = new MissingIndexAnalyzer(0);

        $query = $this->makeQuery('select * from `xray_test_people` where `email` = ?', ['person1@example.com']);
        $query['time'] = 42.5;
        $query['connection'] = 'mysql';

        $findings = $analyzer->analyze([$query]);

        $this->assertNotEmpty($findings, 'Expected at least one finding when the row threshold is 0.');

        foreach ($findings as $finding) {
            $this->assertArrayHasKey('time', $finding, 'A missing_index finding must carry the query time so it persists correctly, not as null.');
            $this->assertArrayHasKey('connection', $finding);
            $this->assertSame(42.5, $finding['time']);
            $this->assertSame('mysql', $finding['connection']);
        }
    }
}