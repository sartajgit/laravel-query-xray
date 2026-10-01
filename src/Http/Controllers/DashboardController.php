<?php

namespace Sartajgit\QueryXray\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Sartajgit\QueryXray\Models\QueryFinding;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('query-xray::dashboard', $this->payload($request));
    }

    public function data(Request $request)
    {
        return response()->json($this->payload($request));
    }

    public function clear(Request $request)
    {
        QueryFinding::query()->delete();

        return response()->json(['status' => 'ok', 'cleared' => 'all']);
    }

    public function clearType(Request $request, string $type)
    {
        $allowed = ['n_plus_one', 'slow_query', 'duplicate_query', 'unoptimized_query'];

        if (! in_array($type, $allowed, true)) {
            return response()->json(['status' => 'error', 'message' => 'Unknown type'], 422);
        }

        QueryFinding::where('type', $type)->delete();

        return response()->json(['status' => 'ok', 'cleared' => $type]);
    }

    protected function payload(Request $request): array
    {
        $topN = (int) config('query-xray.dashboard.top_n', 5);
        [$from, $to] = $this->resolveDateRange($request);
        $search = trim((string) $request->query('search', ''));

        return [
            'filters' => [
                'range' => $request->query('range', '24h'),
                'from' => $from ? $from->toDateTimeString() : null,
                'to' => $to ? $to->toDateTimeString() : null,
                'search' => $search,
            ],
            'stats' => $this->stats($from, $to, $search),
            'top' => [
                'slow_query' => $this->aggregated('slow_query', $topN, false, $from, $to, $search),
                'n_plus_one' => $this->aggregated('n_plus_one', $topN, false, $from, $to, $search),
                'duplicate_query' => $this->aggregated('duplicate_query', $topN, false, $from, $to, $search),
                'unoptimized_query' => $this->aggregated('unoptimized_query', $topN, true, $from, $to, $search),
            ],
            'generated_at' => now()->toDateTimeString(),
            'generated_at_iso' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon|null, 1: \Illuminate\Support\Carbon|null}
     */
    protected function resolveDateRange(Request $request): array
    {
        $range = $request->query('range', '24h');
        $now = now();

        switch ($range) {
            case '1h':
                return [$now->copy()->subHour(), $now];
            case '3h':
                return [$now->copy()->subHours(3), $now];
            case '24h':
                return [$now->copy()->subDay(), $now];
            case '3d':
                return [$now->copy()->subDays(3), $now];
            case 'custom':
                $from = $request->query('from');
                $to = $request->query('to');

                return [
                    $from ? Carbon::parse($from) : $now->copy()->subDay(),
                    $to ? Carbon::parse($to) : $now,
                ];
            case 'all':
            default:
                return [null, null];
        }
    }

    protected function applyFilters($query, ?Carbon $from, ?Carbon $to, string $search)
    {
        if ($from) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to) {
            $query->where('occurred_at', '<=', $to);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sql', 'like', '%'.$search.'%')
                  ->orWhere('file', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    protected function stats(?Carbon $from, ?Carbon $to, string $search): array
    {
        $query = QueryFinding::query();
        $this->applyFilters($query, $from, $to, $search);

        $rows = $query->selectRaw('`type`, count(*) as total')
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

    protected function aggregated(string $type, int $limit, bool $groupByIssue, ?Carbon $from, ?Carbon $to, string $search)
    {
        $groupColumns = ['fingerprint', 'file', 'line'];

        if ($groupByIssue) {
            $groupColumns[] = 'issue';
        }

        $orderColumn = $type === 'slow_query' ? 'max_time_ms' : 'total_count';

        $query = QueryFinding::query()->where('type', $type);
        $this->applyFilters($query, $from, $to, $search);

        $rows = $query
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

        // `last_seen` comes back as a plain "Y-m-d H:i:s" string with no
        // timezone marker. Convert it to an explicit UTC ISO string here so
        // JavaScript never has to guess (and silently guess wrong) what
        // timezone the raw string was in.
        return $rows->map(function ($row) {
            if ($row->last_seen) {
                $row->last_seen_iso = Carbon::parse($row->last_seen, config('app.timezone'))
                    ->utc()
                    ->toIso8601String();
            } else {
                $row->last_seen_iso = null;
            }

            return $row;
        });
    }
}