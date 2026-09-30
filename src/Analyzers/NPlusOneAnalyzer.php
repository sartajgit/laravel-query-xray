<?php

namespace Sartajgit\QueryXray\Analyzers;

class NPlusOneAnalyzer
{
    protected int $threshold;

    public function __construct(int $threshold)
    {
        $this->threshold = $threshold;
    }

    /**
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<int, array<string, mixed>>
     */
    public function analyze(array $queries): array
    {
        $groups = [];

        foreach ($queries as $query) {
            // Framework-only queries (no app file) are not actionable for the developer.
            if ($query['file'] === null) {
                continue;
            }

            $key = $query['fingerprint'].'|'.$query['file'].':'.$query['line'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'query' => $query,
                    'count' => 0,
                    'total_time' => 0.0,
                    'distinct_bindings' => [],
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['total_time'] += $query['time'];
            $groups[$key]['distinct_bindings'][md5(serialize($query['bindings']))] = true;
        }

        $findings = [];

        foreach ($groups as $group) {
            if ($group['count'] < $this->threshold) {
                continue;
            }

            // Identical bindings every time = duplicate query, not N+1.
            if (count($group['distinct_bindings']) < 2) {
                continue;
            }

            $query = $group['query'];

            $findings[] = [
                'type' => 'n_plus_one',
                'sql' => $query['sql'],
                'count' => $group['count'],
                'total_time' => round($group['total_time'], 2),
                'file' => $query['file'],
                'line' => $query['line'],
                'fingerprint' => $query['fingerprint'],
                'suggestion' => 'This query ran '.$group['count'].' times from the same line with different values. '
                    .'Eager load the relationship with ->with(\'relation\'), or fetch all rows at once using whereIn().',
            ];
        }

        return $findings;
    }
}