<?php

namespace Sartajgit\QueryXray\Analyzers;

use Illuminate\Support\Facades\DB;

class MissingIndexAnalyzer
{
    protected int $minRowsToFlag;

    public function __construct(int $minRowsToFlag = 50)
    {
        $this->minRowsToFlag = $minRowsToFlag;
    }

    /**
     * Only called on queries ALREADY flagged by another analyzer (slow,
     * N+1, or duplicate) — never run on every query. EXPLAIN is a real
     * extra database call, so this must stay opt-in and throttled.
     *
     * @param  array<int, array<string, mixed>>  $candidateQueries  raw query records (sql, bindings, file, line, ...)
     * @return array<int, array<string, mixed>>
     */
    public function analyze(array $candidateQueries): array
    {
        $findings = [];
        $seen = [];

        foreach ($candidateQueries as $query) {
            // Only SELECTs are meaningful to EXPLAIN this way; skip the rest.
            if (! preg_match('/^\s*select\b/i', $query['sql'])) {
                continue;
            }

            // Avoid EXPLAINing the exact same query shape twice in one batch.
            $key = $query['fingerprint'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $plan = $this->explain($query['sql'], $query['bindings']);

            if ($plan === null) {
                continue;
            }

            foreach ($plan as $row) {
                $usesIndex = ! empty($row->key);
                $isFullScan = isset($row->type) && strtolower($row->type) === 'all';
                $rowsScanned = (int) ($row->rows ?? 0);

                if ($usesIndex && ! $isFullScan) {
                    continue;
                }

                if ($rowsScanned < $this->minRowsToFlag) {
                    continue; // table too small for an index to matter
                }

                $findings[] = [
                    'type' => 'missing_index',
                    'sql' => $query['sql'],
                    'bindings' => $query['bindings'],
                    'time' => $query['time'] ?? null,
                    'connection' => $query['connection'] ?? null,
                    'file' => $query['file'],
                    'line' => $query['line'],
                    'fingerprint' => $query['fingerprint'],
                    'table' => $row->table ?? null,
                    'rows_scanned' => $rowsScanned,
                    'scan_type' => $row->type ?? 'unknown',
                    'suggestion' => $this->buildSuggestion($query['sql'], $row->table ?? null, $rowsScanned),
                ];

                break; // one finding per query is enough, even if multiple tables are joined
            }
        }

        return $findings;
    }

    /**
     * @return array<int, object>|null  EXPLAIN result rows, or null if EXPLAIN itself failed
     */
    protected function explain(string $sql, array $bindings): ?array
    {
        try {
            return DB::select('EXPLAIN '.$sql, $bindings);
        } catch (\Throwable $e) {
            // EXPLAIN can fail on some driver-specific syntax — fail safe, skip it.
            return null;
        }
    }

    protected function buildSuggestion(string $sql, ?string $table, int $rowsScanned): string
    {
        $columns = $this->guessFilterColumns($sql);

        if (empty($columns)) {
            return "This query scanned approximately {$rowsScanned} rows without using an index on `{$table}`. Review the WHERE/JOIN/ORDER BY clauses and add an appropriate index.";
        }

        $columnList = implode('`, `', $columns);

        if (count($columns) > 1) {
            return "This query scanned approximately {$rowsScanned} rows on `{$table}` without using an index. Consider a COMPOSITE index: `ALTER TABLE `{$table}` ADD INDEX (`{$columnList}`)` — column order should match how they're filtered, most selective first.";
        }

        return "This query scanned approximately {$rowsScanned} rows on `{$table}` without using an index. Consider: `ALTER TABLE `{$table}` ADD INDEX (`{$columnList}`)`.";
    }

    /**
     * Best-effort extraction of WHERE/JOIN column names, purely to make the
     * suggestion more specific. Not guaranteed exhaustive or always correct —
     * always have a developer verify the actual columns before adding an index.
     *
     * @return string[]
     */
    protected function guessFilterColumns(string $sql): array
    {
        $columns = [];

        if (preg_match_all('/`?([a-zA-Z_][a-zA-Z0-9_]*)`?\s*(?:=|<=>|<>|!=|<=|>=|<|>|like|in\s*\()/i', $sql, $matches)) {
            foreach ($matches[1] as $col) {
                $col = strtolower($col);
                if (! in_array($col, $columns, true)) {
                    $columns[] = $col;
                }
            }
        }

        return array_slice($columns, 0, 3); // keep suggestions readable
    }
}