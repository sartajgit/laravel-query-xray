<?php

namespace Sartajgit\QueryXray\Tests\Analyzers;

use Sartajgit\QueryXray\Analyzers\NPlusOneAnalyzer;
use Sartajgit\QueryXray\Support\QueryFingerprint;
use Sartajgit\QueryXray\Tests\TestCase;

class NPlusOneAnalyzerTest extends TestCase
{
    protected function makeQuery(array $bindings, ?string $file = 'app/Http/Controllers/UserController.php', int $line = 20): array
    {
        $sql = 'select * from `users` where `id` = ?';

        return [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => 1.0,
            'connection' => 'mysql',
            'file' => $file,
            'line' => $line,
            'fingerprint' => QueryFingerprint::make($sql),
        ];
    }

    public function test_same_line_different_values_flagged_as_n_plus_one(): void
    {
        $analyzer = new NPlusOneAnalyzer(3);

        $queries = [
            $this->makeQuery([1]),
            $this->makeQuery([2]),
            $this->makeQuery([3]),
            $this->makeQuery([4]),
        ];

        $findings = $analyzer->analyze($queries);

        $this->assertCount(1, $findings);
        $this->assertSame('n_plus_one', $findings[0]['type']);
        $this->assertSame(4, $findings[0]['count']);
    }

    public function test_same_line_same_value_is_not_flagged_as_n_plus_one(): void
    {
        $analyzer = new NPlusOneAnalyzer(3);

        $queries = [
            $this->makeQuery([1]),
            $this->makeQuery([1]),
            $this->makeQuery([1]),
        ];

        $findings = $analyzer->analyze($queries);

        $this->assertCount(0, $findings, 'Identical bindings every time is a duplicate, not N+1 — must not be flagged here.');
    }

    public function test_below_threshold_is_not_flagged(): void
    {
        $analyzer = new NPlusOneAnalyzer(3);

        $queries = [
            $this->makeQuery([1]),
            $this->makeQuery([2]),
        ];

        $findings = $analyzer->analyze($queries);

        $this->assertCount(0, $findings);
    }

    public function test_queries_with_no_file_are_ignored(): void
    {
        $analyzer = new NPlusOneAnalyzer(3);

        $queries = [
            $this->makeQuery([1], null, 0),
            $this->makeQuery([2], null, 0),
            $this->makeQuery([3], null, 0),
            $this->makeQuery([4], null, 0),
        ];

        $findings = $analyzer->analyze($queries);

        $this->assertCount(0, $findings, 'Framework-internal queries with no app file must never be flagged.');
    }
}