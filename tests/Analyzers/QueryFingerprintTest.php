<?php

namespace Sartajgit\QueryXray\Tests\Analyzers;

use Sartajgit\QueryXray\Support\QueryFingerprint;
use Sartajgit\QueryXray\Tests\TestCase;

class QueryFingerprintTest extends TestCase
{
    public function test_same_shape_different_values_produce_same_fingerprint(): void
    {
        $a = QueryFingerprint::make('select * from `users` where `id` = 1');
        $b = QueryFingerprint::make('select * from `users` where `id` = 2');

        $this->assertSame($a, $b);
    }

    public function test_different_shapes_produce_different_fingerprints(): void
    {
        $a = QueryFingerprint::make('select * from `users` where `id` = 1');
        $b = QueryFingerprint::make('select * from `posts` where `id` = 1');

        $this->assertNotSame($a, $b);
    }

    public function test_in_list_of_any_length_collapses_to_same_shape(): void
    {
        $a = QueryFingerprint::make('select * from `users` where `id` in (1, 2, 3)');
        $b = QueryFingerprint::make('select * from `users` where `id` in (1, 2, 3, 4, 5)');

        $this->assertSame($a, $b);
    }

    public function test_whitespace_differences_do_not_change_fingerprint(): void
    {
        $a = QueryFingerprint::make('select * from users where id = 1');
        $b = QueryFingerprint::make('select   *   from   users   where   id  =  1');

        $this->assertSame($a, $b);
    }
}