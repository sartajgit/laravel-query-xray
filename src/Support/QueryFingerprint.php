<?php

namespace Sartajgit\QueryXray\Support;

class QueryFingerprint
{
    public static function make(string $sql): string
    {
        return md5(self::normalize($sql));
    }

    /**
     * Reduce a query to its "shape": lowercase, literals and numbers
     * replaced by "?", IN lists collapsed, whitespace squeezed.
     */
    public static function normalize(string $sql): string
    {
        $sql = strtolower($sql);
        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $sql);
        $sql = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql);
        $sql = preg_replace('/\(\s*\?(?:\s*,\s*\?)+\s*\)/', '(?)', $sql);
        $sql = preg_replace('/\s+/', ' ', $sql);

        return trim($sql);
    }
}
