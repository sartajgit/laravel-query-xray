<?php

namespace Sartajgit\QueryXray\Support;

class SensitiveDataMasker
{
    /** @var string[] */
    protected array $patterns;

    /** @var string[] lowercase "table.column" pairs */
    protected array $exactColumns;

    protected string $maskValue;

    public function __construct(array $patterns, array $exactColumns = [], string $maskValue = '***MASKED***')
    {
        $this->patterns = $patterns;
        $this->exactColumns = array_map('strtolower', $exactColumns);
        $this->maskValue = $maskValue;
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @return array<int, mixed>
     */
    public function mask(string $sql, array $bindings): array
    {
        if (empty($bindings)) {
            return $bindings;
        }

        $table = $this->extractPrimaryTable($sql);
        $columns = $this->extractColumnsForPlaceholders($sql);
        $masked = [];

        foreach ($bindings as $i => $value) {
            $column = $columns[$i] ?? null;

            $isSensitive = ($column !== null && $this->isSensitiveByPattern($column))
                || ($column !== null && $table !== null && $this->isSensitiveByExactColumn($table, $column));

            $masked[$i] = $isSensitive ? $this->maskValue : $value;
        }

        return $masked;
    }

    protected function isSensitiveByPattern(string $column): bool
    {
        if (empty($this->patterns)) {
            return false;
        }

        $column = strtolower($column);

        foreach ($this->patterns as $pattern) {
            if (strpos($column, strtolower($pattern)) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function isSensitiveByExactColumn(string $table, string $column): bool
    {
        if (empty($this->exactColumns)) {
            return false;
        }

        $key = strtolower($table).'.'.strtolower($column);

        return in_array($key, $this->exactColumns, true);
    }

    protected function extractPrimaryTable(string $sql): ?string
    {
        if (preg_match('/\b(?:from|into|update)\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
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