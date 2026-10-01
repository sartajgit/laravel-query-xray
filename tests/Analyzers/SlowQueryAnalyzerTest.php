<?php

namespace Sartajgit\QueryXray\Tests\Analyzers;

use Sartajgit\QueryXray\Analyzers\SlowQueryAnalyzer;
use Sartajgit\QueryXray\Support\QueryFingerprint;
use Sartajgit\QueryXray\Tests\TestCase;

class SlowQueryAnalyzerTest extends TestCase
{
    protected function makeQuery(string $sql, float $time, ?string $file = 'routes/web.php', ?int $line = 10): array
    {
        return [
            'sql' => $sql,
            'bindings' => [],
            'time' => $time,
            'connection' => 'mysql',
            'file' => $file,
            'line' => $line,
            'fingerprint' => QueryFingerprint::make($sql),
        ];
    }

    public function test_query_above_threshold_is_flagged(): void
    {
        $analyzer = new SlowQueryAnalyzer(100.0);
        $findings = $analyzer->analyze([$this->makeQuery('select 1', 150.0)]);

        $this->assertCount(1, $findings);
        $this->assertSame('slow_query', $findings[0]['type']);
    }

    public function test_query_below_threshold_is_not_flagged(): void
    {
        $analyzer = new SlowQueryAnalyzer(100.0);
        $findings = $analyzer->analyze([$this->makeQuery('select 1', 50.0)]);

        $this->assertCount(0, $findings);
    }

    public function test_query_exactly_at_threshold_is_flagged(): void
    {
        $analyzer = new SlowQueryAnalyzer(100.0);
        $findings = $analyzer->analyze([$this->makeQuery('select 1', 100.0)]);

        $this->assertCount(1, $findings, 'A query exactly at the threshold should count as slow (>=), not be excluded.');
    }
}