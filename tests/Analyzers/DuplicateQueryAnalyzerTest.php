<?php

namespace Sartajgit\QueryXray\Tests\Analyzers;

use Sartajgit\QueryXray\Analyzers\DuplicateQueryAnalyzer;
use Sartajgit\QueryXray\Support\QueryFingerprint;
use Sartajgit\QueryXray\Tests\TestCase;

class DuplicateQueryAnalyzerTest extends TestCase
{
    protected function makeQuery(array $bindings, ?string $file = 'app/Services/ReportService.php'): array
    {
        $sql = 'select * from `users` where `email` = ?';

        return [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => 1.0,
            'connection' => 'mysql',
            'file' => $file,
            'line' => 15,
            'fingerprint' => QueryFingerprint::make($sql),
        ];
    }

    public function test_exact_repeat_is_flagged_as_duplicate(): void
    {
        $analyzer = new DuplicateQueryAnalyzer(2);

        $findings = $analyzer->analyze([
            $this->makeQuery(['a@b.com']),
            $this->makeQuery(['a@b.com']),
            $this->makeQuery(['a@b.com']),
        ]);

        $this->assertCount(1, $findings);
        $this->assertSame('duplicate_query', $findings[0]['type']);
        $this->assertSame(3, $findings[0]['count']);
    }

    public function test_different_values_are_not_flagged_as_duplicate(): void
    {
        $analyzer = new DuplicateQueryAnalyzer(2);

        $findings = $analyzer->analyze([
            $this->makeQuery(['a@b.com']),
            $this->makeQuery(['c@d.com']),
        ]);

        $this->assertCount(0, $findings);
    }

    public function test_framework_internal_queries_are_ignored(): void
    {
        $analyzer = new DuplicateQueryAnalyzer(2);

        $findings = $analyzer->analyze([
            $this->makeQuery(['x'], null),
            $this->makeQuery(['x'], null),
        ]);

        $this->assertCount(0, $findings, 'A repeated framework query (sessions, etc.) must not be reported as a duplicate.');
    }
}