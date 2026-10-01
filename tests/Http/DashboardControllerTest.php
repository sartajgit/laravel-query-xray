<?php

namespace Sartajgit\QueryXray\Tests\Http;

use Sartajgit\QueryXray\Models\QueryFinding;
use Sartajgit\QueryXray\Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    protected function insertFinding(array $overrides = []): QueryFinding
    {
        return QueryFinding::create(array_merge([
            'type' => 'slow_query',
            'issue' => null,
            'fingerprint' => 'fp'.uniqid(),
            'sql' => 'select * from `users` where `id` = ?',
            'bindings' => json_encode([1]),
            'count' => 1,
            'time_ms' => 150.0,
            'connection' => 'testing',
            'file' => 'app/Http/Controllers/TestController.php',
            'line' => 10,
            'http_method' => 'GET',
            'url' => 'http://localhost/test',
            'suggestion' => 'Test suggestion',
            'occurred_at' => now(),
        ], $overrides));
    }

    public function test_dashboard_route_loads_successfully(): void
    {
        $response = $this->get('/query-xray');

        $response->assertStatus(200);
    }

    public function test_data_endpoint_returns_expected_json_shape(): void
    {
        $this->insertFinding();

        $response = $this->getJson('/query-xray/data');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'stats' => ['n_plus_one', 'slow_query', 'duplicate_query', 'unoptimized_query', 'missing_index', 'total'],
            'top' => ['slow_query', 'n_plus_one', 'duplicate_query', 'unoptimized_query', 'missing_index'],
            'generated_at',
        ]);
    }

    public function test_stats_count_only_findings_within_the_selected_time_range(): void
    {
        $this->insertFinding(['occurred_at' => now()->subHours(2)]); // outside 1h window
        $this->insertFinding(['occurred_at' => now()->subMinutes(10)]); // inside 1h window

        $response = $this->getJson('/query-xray/data?range=1h');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('stats.slow_query'));
    }

    public function test_all_time_range_includes_old_findings(): void
    {
        $this->insertFinding(['occurred_at' => now()->subDays(10)]);

        $response = $this->getJson('/query-xray/data?range=all');

        $this->assertSame(1, $response->json('stats.slow_query'));
    }

    public function test_search_filters_by_sql_text(): void
    {
        $this->insertFinding(['sql' => 'select * from `orders` where `id` = ?']);
        $this->insertFinding(['sql' => 'select * from `users` where `id` = ?']);

        $response = $this->getJson('/query-xray/data?range=all&search=orders');

        $this->assertSame(1, $response->json('stats.slow_query'));
    }

    public function test_repeated_same_issue_collapses_into_one_aggregated_row(): void
    {
        // Same fingerprint + file + line, inserted 3 separate times —
        // must appear as ONE row in "top", with occurrences = 3.
        $this->insertFinding(['fingerprint' => 'same-fp', 'file' => 'app/X.php', 'line' => 5]);
        $this->insertFinding(['fingerprint' => 'same-fp', 'file' => 'app/X.php', 'line' => 5]);
        $this->insertFinding(['fingerprint' => 'same-fp', 'file' => 'app/X.php', 'line' => 5]);

        $response = $this->getJson('/query-xray/data?range=all');

        $topSlow = $response->json('top.slow_query');
        $this->assertCount(1, $topSlow, 'Three identical findings must collapse into one aggregated row.');
        $this->assertSame(3, $topSlow[0]['occurrences']);
    }

    public function test_clear_all_removes_every_finding(): void
    {
        $this->insertFinding();
        $this->insertFinding();

        $response = $this->delete('/query-xray/clear');

        $response->assertStatus(200);
        $this->assertSame(0, QueryFinding::count());
    }

    public function test_clear_type_only_removes_that_type(): void
    {
        $this->insertFinding(['type' => 'slow_query']);
        $this->insertFinding(['type' => 'n_plus_one']);

        $response = $this->delete('/query-xray/clear/slow_query');

        $response->assertStatus(200);
        $this->assertSame(0, QueryFinding::where('type', 'slow_query')->count());
        $this->assertSame(1, QueryFinding::where('type', 'n_plus_one')->count());
    }

    public function test_clear_type_rejects_unknown_type(): void
    {
        $response = $this->delete('/query-xray/clear/not_a_real_type');

        $response->assertStatus(422);
    }
}