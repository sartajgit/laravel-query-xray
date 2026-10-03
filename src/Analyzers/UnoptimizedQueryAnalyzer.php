<?php

namespace Sartajgit\QueryXray\Analyzers;

class UnoptimizedQueryAnalyzer
{
    /**
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<int, array<string, mixed>>
     */
    public function analyze(array $queries): array
    {
        $findings = [];

        foreach ($queries as $query) {
            // Framework-only queries aren't actionable for the developer.
            if ($query['file'] === null) {
                continue;
            }

            if (preg_match('/^select\s+\*\s+from/i', $query['sql'])) {
                $findings[] = $this->finding($query, 'select_star',
                    'Select only the columns you need instead of *. This reduces memory usage and network transfer, especially on wide tables.');
            }

            if ($this->hasLeadingWildcardLike($query['sql'], $query['bindings'])) {
                $findings[] = $this->finding($query, 'leading_wildcard_like',
                    "A LIKE pattern starting with % cannot use a standard index and forces a full table scan. Consider full-text search, or restructure to 'value%' if a prefix match is possible.");
            }

            if (preg_match('/^select(?!.*\blimit\b).*from\s+`?\w+`?\s*$/is', $query['sql'])) {
                $findings[] = $this->finding($query, 'missing_limit',
                    'This SELECT has no LIMIT and no WHERE clause. If this table grows, this query will pull every row into memory. Add pagination or a LIMIT.');
            }
        }

        return $findings;
    }

    /**
     * Laravel logs queries with "?" placeholders — the real value lives in
     * bindings, not in the SQL string — so LIKE patterns must be checked there.
     */
    protected function hasLeadingWildcardLike(string $sql, array $bindings): bool
    {
        if (stripos($sql, 'like') === false) {
            return false;
        }

        foreach ($bindings as $binding) {
            if (is_string($binding) && strpos($binding, '%') === 0) {
                return true;
            }
        }

        return false;
    }

    protected function finding(array $query, string $issue, string $suggestion): array
    {
        return [
            'type' => 'unoptimized_query',
            'issue' => $issue,
            'sql' => $query['sql'],
            'bindings' => $query['bindings'],
            'time' => $query['time'] ?? null,
            'connection' => $query['connection'] ?? null,
            'file' => $query['file'],
            'line' => $query['line'],
            'fingerprint' => $query['fingerprint'],
            'suggestion' => $suggestion,
        ];
    }
}