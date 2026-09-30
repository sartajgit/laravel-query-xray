<?php

namespace Sartajgit\QueryXray\Analyzers;

class SlowQueryAnalyzer
{
    protected float $thresholdMs;

    public function __construct(float $thresholdMs)
    {
        $this->thresholdMs = $thresholdMs;
    }

    /**
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<int, array<string, mixed>>
     */
    public function analyze(array $queries): array
    {
        $findings = [];

        foreach ($queries as $query) {
            if ($query['time'] < $this->thresholdMs) {
                continue;
            }

            $findings[] = [
                'type' => 'slow_query',
                'sql' => $query['sql'],
                'bindings' => $query['bindings'],
                'time' => $query['time'],
                'file' => $query['file'],
                'line' => $query['line'],
                'fingerprint' => $query['fingerprint'],
                'suggestion' => 'Run EXPLAIN on this query. Check that WHERE / JOIN / ORDER BY columns are indexed, and select only the columns you need.',
            ];
        }

        return $findings;
    }
}
