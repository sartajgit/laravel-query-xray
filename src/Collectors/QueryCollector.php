<?php

namespace Sartajgit\QueryXray\Collectors;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Sartajgit\QueryXray\Analyzers\DuplicateQueryAnalyzer;
use Sartajgit\QueryXray\Analyzers\NPlusOneAnalyzer;
use Sartajgit\QueryXray\Analyzers\SlowQueryAnalyzer;
use Sartajgit\QueryXray\Analyzers\UnoptimizedQueryAnalyzer;
use Sartajgit\QueryXray\Models\QueryFinding;
use Sartajgit\QueryXray\Support\BacktraceResolver;
use Sartajgit\QueryXray\Support\QueryFingerprint;
use Sartajgit\QueryXray\Support\SensitiveDataMasker;

class QueryCollector
{
    /** @var array<int, array<string, mixed>> */
    protected array $queries = [];

    protected BacktraceResolver $resolver;

    public function __construct(?BacktraceResolver $resolver = null)
    {
        $this->resolver = $resolver ?: new BacktraceResolver();
    }

    public function record(QueryExecuted $query): void
    {
        $origin = $this->resolver->resolve();

        // NOTE: raw, unmasked bindings are kept here deliberately — the
        // N+1 and duplicate analyzers need real values to tell queries
        // apart correctly. Masking is applied later, only at persist().
        $this->queries[] = [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
            'connection' => $query->connectionName,
            'file' => $origin['file'] ?? null,
            'line' => $origin['line'] ?? null,
            'fingerprint' => QueryFingerprint::make($query->sql),
        ];
    }

    public function all(): array
    {
        return $this->queries;
    }

    public function grouped(): array
    {
        $groups = [];

        foreach ($this->queries as $query) {
            $key = $query['fingerprint'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'sql' => $query['sql'],
                    'count' => 0,
                    'total_time' => 0.0,
                    'file' => $query['file'],
                    'line' => $query['line'],
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['total_time'] += $query['time'];
        }

        return array_values($groups);
    }

    public function findings(): array
    {
        $slow = new SlowQueryAnalyzer((float) config('query-xray.slow_threshold_ms', 100));
        $nPlusOne = new NPlusOneAnalyzer((int) config('query-xray.n_plus_one_threshold', 3));
        $duplicate = new DuplicateQueryAnalyzer();
        $unoptimized = new UnoptimizedQueryAnalyzer();

        return array_merge(
            $slow->analyze($this->queries),
            $nPlusOne->analyze($this->queries),
            $duplicate->analyze($this->queries),
            $unoptimized->analyze($this->queries)
        );
    }

    public function stats(): array
    {
        $stats = [];

        foreach ($this->findings() as $finding) {
            $type = $finding['type'];
            $stats[$type] = ($stats[$type] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Write every finding from this request into the database.
     * Called from ServiceProvider::terminating(), after the response
     * has already been sent to the browser — adds no latency to the request.
     */
    public function persist(): void
    {
        $findings = $this->findings();

        if (empty($findings)) {
            return;
        }

        $masker = new SensitiveDataMasker(config('query-xray.sensitive_patterns', []));

        $now = now()->toDateTimeString();
        $method = null;
        $url = null;

        try {
            $method = Request::method();
            $url = Request::fullUrl();
        } catch (\Throwable $e) {
            // No HTTP request in context (e.g. artisan command) — leave null.
        }

        $rows = [];

        foreach ($findings as $finding) {
            $bindings = $finding['bindings'] ?? [];
            $maskedBindings = $masker->mask($finding['sql'], $bindings);

            $rows[] = [
                'type' => $finding['type'],
                'issue' => $finding['issue'] ?? null,
                'fingerprint' => $finding['fingerprint'],
                'sql' => $finding['sql'],
                'bindings' => json_encode($maskedBindings),
                'count' => $finding['count'] ?? 1,
                'time_ms' => $finding['time'] ?? $finding['total_time'] ?? null,
                'connection' => $finding['connection'] ?? null,
                'file' => $finding['file'] ?? null,
                'line' => $finding['line'] ?? null,
                'http_method' => $method,
                'url' => $url,
                'suggestion' => $finding['suggestion'] ?? null,
                'occurred_at' => $now,
            ];
        }

        try {
            QueryFinding::insert($rows);
        } catch (\Throwable $e) {
            Log::error('QueryXray: failed to persist findings', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}