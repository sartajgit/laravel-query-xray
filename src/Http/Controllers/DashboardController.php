<?php

namespace Sartajgit\QueryXray\Http\Controllers;

use Illuminate\Routing\Controller;
use Sartajgit\QueryXray\Models\QueryFinding;

class DashboardController extends Controller
{
    public function index()
    {
        return view('query-xray::dashboard', $this->payload());
    }

    public function data()
    {
        return response()->json($this->payload());
    }

    protected function payload(): array
    {
        $topN = (int) config('query-xray.dashboard.top_n', 5);

        return [
            'stats' => $this->stats(),
            'top' => [
                'slow_query' => $this->aggregated('slow_query', $topN),
                'n_plus_one' => $this->aggregated('n_plus_one', $topN),
                'duplicate_query' => $this->aggregated('duplicate_query', $topN),
                'unoptimized_query' => $this->aggregated('unoptimized_query', $topN, true),
            ],
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    protected function stats(): array
    {
        $rows = QueryFinding::selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'n_plus_one' => (int) ($rows['n_plus_one'] ?? 0),
            'slow_query' => (int) ($rows['slow_query'] ?? 0),
            'duplicate_query' => (int) ($rows['duplicate_query'] ?? 0),
            'unoptimized_query' => (int) ($rows['unoptimized_query'] ?? 0),
            'total' => (int) $rows->sum(),
        ];
    }

    /**
     * Collapse repeated detections of the SAME issue (same query shape,
     * same file, same line) into ONE row, showing how many times it's
     * been seen and the worst timing observed.
     */
    protected function aggregated(string $type, int $limit, bool $groupByIssue = false)
    {
        $groupColumns = ['fingerprint', 'file', 'line'];

        if ($groupByIssue) {
            $groupColumns[] = 'issue';
        }

        $orderColumn = $type === 'slow_query' ? 'max_time_ms' : 'total_count';

        return QueryFinding::query()
            ->where('type', $type)
            ->selectRaw(
                implode(', ', $groupColumns).', '.
                'MAX(`sql`) as `sql`, '.
                'MAX(`issue`) as `issue`, '.
                'MAX(`suggestion`) as `suggestion`, '.
                'COUNT(*) as `occurrences`, '.
                'SUM(`count`) as `total_count`, '.
                'MAX(`time_ms`) as `max_time_ms`, '.
                'MAX(`occurred_at`) as `last_seen`'
            )
            ->groupBy($groupColumns)
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->get();
    }
}