<?php

namespace Sartajgit\QueryXray\Tests\Analyzers;

use Sartajgit\QueryXray\Analyzers\UnoptimizedQueryAnalyzer;
use Sartajgit\QueryXray\Support\QueryFingerprint;
use Sartajgit\QueryXray\Tests\TestCase;

class UnoptimizedQueryAnalyzerTest extends TestCase
{
    protected function makeQuery(string $sql, array $bindings = [], ?string $file = 'app/Models/User.php'): array
    {
        return [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => 1.0,
            'connection' => 'mysql',
            'file' => $file,
            'line' => 5,
            'fingerprint' => QueryFingerprint::make($sql),
        ];
    }

    public function test_select_star_is_flagged(): void
    {
        $analyzer = new UnoptimizedQueryAnalyzer();
        $findings = $analyzer->analyze([$this->makeQuery('select * from `users` where `id` = ?', [1])]);

        $this->assertCount(1, $findings);
        $this->assertSame('select_star', $findings[0]['issue']);
    }

    public function test_specific_columns_are_not_flagged_as_select_star(): void
    {
        $analyzer = new UnoptimizedQueryAnalyzer();
        $findings = $analyzer->analyze([$this->makeQuery('select `id`, `name` from `users` where `id` = ?', [1])]);

        $this->assertCount(0, $findings);
    }

    public function test_leading_wildcard_like_is_flagged_via_binding_not_sql_text(): void
    {
        $analyzer = new UnoptimizedQueryAnalyzer();
        $findings = $analyzer->analyze([
            $this->makeQuery('select `id` from `users` where `name` like ?', ['%john']),
        ]);

        $this->assertCount(1, $findings);
        $this->assertSame('leading_wildcard_like', $findings[0]['issue']);
    }

    public function test_trailing_wildcard_like_is_not_flagged(): void
    {
        $analyzer = new UnoptimizedQueryAnalyzer();
        $findings = $analyzer->analyze([
            $this->makeQuery('select `id` from `users` where `name` like ?', ['john%']),
        ]);

        $this->assertCount(0, $findings, 'A trailing wildcard (john%) CAN use an index and must not be flagged.');
    }

    public function test_missing_limit_on_full_table_select_is_flagged(): void
    {
        $analyzer = new UnoptimizedQueryAnalyzer();
        $findings = $analyzer->analyze([$this->makeQuery('select * from users')]);

        $this->assertCount(2, $findings);
        $issues = array_column($findings, 'issue');
        $this->assertContains('select_star', $issues);
        $this->assertContains('missing_limit', $issues);
    }

    public function test_select_with_limit_is_not_flagged_for_missing_limit(): void
    {
        $analyzer = new UnoptimizedQueryAnalyzer();
        $findings = $analyzer->analyze([$this->makeQuery('select * from `users` limit 10')]);

        $issues = array_column($findings, 'issue');
        $this->assertNotContains('missing_limit', $issues);
    }

    public function test_framework_internal_queries_are_ignored(): void
    {
        $analyzer = new UnoptimizedQueryAnalyzer();
        $findings = $analyzer->analyze([$this->makeQuery('select * from `sessions` where `id` = ?', ['abc'], null)]);

        $this->assertCount(0, $findings);
    }
}