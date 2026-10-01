<?php

namespace Sartajgit\QueryXray\Support;

class SensitiveDataMasker
{
    /** @var string[] */
    protected array $patterns;

    protected string $maskValue;

    public function __construct(array $patterns, string $maskValue = '***MASKED***')
    {
        $this->patterns = $patterns;
        $this->maskValue = $maskValue;
    }

    /**
     * Return a copy of $bindings with any value whose governing column name
     * matches a sensitive pattern replaced by the mask placeholder.
     *
     * Known limitation: this matches by COLUMN NAME only. A sensitive value
     * stored under a generic column name (e.g. Laravel's `sessions`.`id`,
     * which holds a session token) will NOT be masked, since "id" itself
     * isn't a sensitive-looking name. Add table-specific rules if you need
     * to cover that case.
     *
     * @param  array<int, mixed>  $bindings
     * @return array<int, mixed>
     */
    public function mask(string $sql, array $bindings): array
    {
        if (empty($bindings) || empty($this->patterns)) {
            return $bindings;
        }

        $columns = $this->extractColumnsForPlaceholders($sql);
        $masked = [];

        foreach ($bindings as $i => $value) {
            $column = $columns[$i] ?? null;
            $masked[$i] = ($column !== null && $this->isSensitive($column))
                ? $this->maskValue
                : $value;
        }

        return $masked;
    }

    protected function isSensitive(string $column): bool
    {
        $column = strtolower($column);

        foreach ($this->patterns as $pattern) {
            if (strpos($column, strtolower($pattern)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Walk the SQL and, for each "?" placeholder in order, find the column
     * name that governs it — the identifier immediately before the nearest
     * preceding comparison operator or an "IN (" list opener. An IN-list
     * with multiple placeholders correctly maps every placeholder in the
     * list back to that same single column.
     *
     * @return array<int, string|null>
     */
    protected function extractColumnsForPlaceholders(string $sql): array
    {
        $columnPattern = '/(`?[a-zA-Z_][a-zA-Z0-9_]*`?)\s*(=|<=>|<>|!=|<=|>=|<|>|like|not\s+like)\s*|(`?[a-zA-Z_][a-zA-Z0-9_]*`?)\s+in\s*\(/i';

        preg_match_all($columnPattern, $sql, $colMatches, PREG_OFFSET_CAPTURE);

        $colEvents = [];
        foreach ($colMatches[0] as $i => $full) {
            $col = $colMatches[1][$i][0] !== '' ? $colMatches[1][$i][0] : $colMatches[3][$i][0];
            $colEvents[] = [
                'offset' => $full[1],
                'column' => trim($col, '`'),
            ];
        }

        preg_match_all('/\?/', $sql, $qMarks, PREG_OFFSET_CAPTURE);

        $columns = [];

        foreach ($qMarks[0] as $q) {
            $qOffset = $q[1];
            $nearestColumn = null;

            foreach ($colEvents as $event) {
                if ($event['offset'] < $qOffset) {
                    $nearestColumn = $event['column'];
                } else {
                    break;
                }
            }

            $columns[] = $nearestColumn;
        }

        return $columns;
    }
}