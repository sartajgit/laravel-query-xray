<?php

namespace Sartajgit\QueryXray\Tests\Console;

use Sartajgit\QueryXray\Models\QueryFinding;
use Sartajgit\QueryXray\Tests\TestCase;

class PruneCommandTest extends TestCase
{
    protected function insertFinding(array $overrides = []): QueryFinding
    {
        return QueryFinding::create(array_merge([
            'type' => 'slow_query',
            'fingerprint' => 'fp'.uniqid(),
            'sql' => 'select 1',
            'bindings' => json_encode([]),
            'count' => 1,
            'occurred_at' => now(),
        ], $overrides));
    }

    public function test_prunes_findings_older_than_configured_days(): void
    {
        config(['query-xray.retention_days' => 7]);

        $this->insertFinding(['occurred_at' => now()->subDays(10)]); // old — should be pruned
        $this->insertFinding(['occurred_at' => now()->subDays(2)]);  // recent — should remain

        $this->artisan('query-xray:prune')->assertSuccessful();

        $this->assertSame(1, QueryFinding::count());
    }

    public function test_days_option_overrides_config(): void
    {
        config(['query-xray.retention_days' => 30]);

        $this->insertFinding(['occurred_at' => now()->subDays(5)]);

        $this->artisan('query-xray:prune', ['--days' => 1])->assertSuccessful();

        $this->assertSame(0, QueryFinding::count(), 'The --days option must override the config value.');
    }

    public function test_does_nothing_when_nothing_is_old_enough(): void
    {
        $this->insertFinding(['occurred_at' => now()]);

        $this->artisan('query-xray:prune')->assertSuccessful();

        $this->assertSame(1, QueryFinding::count());
    }
}