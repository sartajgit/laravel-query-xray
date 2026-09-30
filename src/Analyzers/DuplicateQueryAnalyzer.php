<?php

namespace Sartajgit\QueryXray\Analyzers;

class DuplicateQueryAnalyzer
{
    protected int $threshold;

    public function __construct(int $threshold = 2)
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
            // Framework-only queries (no app file) aren't actionable for the developer.
            if ($query['file'] === null) {
                continue;
            }

            // Exact duplicate = same shape AND same bindings.
            $key = $query['fingerprint'].'|'.md5(serialize($query['bindings']));

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'query' => $query,
                    'count' => 0,
                    'total_time' => 0.0,
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['total_time'] += $query['time'];
        }

        $findings = [];

        foreach ($groups as $group) {
            if ($group['count'] < $this->threshold) {
                continue;
            }

            $query = $group['query'];

            $findings[] = [
                'type' => 'duplicate_query',
                'sql' => $query['sql'],
                'bindings' => $query['bindings'],
                'count' => $group['count'],
                'total_time' => round($group['total_time'], 2),
                'file' => $query['file'],
                'line' => $query['line'],
                'fingerprint' => $query['fingerprint'],
                'suggestion' => 'The exact same query ran '.$group['count'].' times with identical values in this request. '
                    .'Cache the result in a variable and reuse it, or use Cache::remember() if it happens across requests too.',
            ];
        }

        return $findings;
    }
}