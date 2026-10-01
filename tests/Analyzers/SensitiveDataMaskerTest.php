<?php

namespace Sartajgit\QueryXray\Tests\Support;

use Sartajgit\QueryXray\Support\SensitiveDataMasker;
use Sartajgit\QueryXray\Tests\TestCase;

class SensitiveDataMaskerTest extends TestCase
{
    protected function masker(): SensitiveDataMasker
    {
        return new SensitiveDataMasker([
            'password', 'token', 'secret', 'api_key', 'apikey',
            'credit_card', 'card_number', 'cvv', 'cvc', 'ssn', 'otp',
        ]);
    }

    public function test_id_column_is_not_masked(): void
    {
        $result = $this->masker()->mask('select * from `users` where `id` = ?', [1]);
        $this->assertSame([1], $result);
    }

    public function test_password_column_is_masked(): void
    {
        $result = $this->masker()->mask('select * from `users` where `password` = ?', ['secret123']);
        $this->assertSame(['***MASKED***'], $result);
    }

    public function test_mixed_sensitive_and_normal_columns(): void
    {
        $result = $this->masker()->mask(
            'select * from `users` where `email` = ? and `password_hash` = ?',
            ['a@b.com', 'abc123']
        );

        $this->assertSame(['a@b.com', '***MASKED***'], $result);
    }

    public function test_update_statement_masks_correct_column_only(): void
    {
        $result = $this->masker()->mask(
            'update `users` set `api_token` = ?, `name` = ? where `id` = ?',
            ['tok_abc', 'John', 5]
        );

        $this->assertSame(['***MASKED***', 'John', 5], $result);
    }

    public function test_in_list_values_are_not_masked_for_normal_column(): void
    {
        $result = $this->masker()->mask(
            'select * from `orders` where `user_id` in (?, ?, ?, ?)',
            [1, 2, 3, 4]
        );

        $this->assertSame([1, 2, 3, 4], $result);
    }

    public function test_in_list_masks_every_value_for_sensitive_column(): void
    {
        $result = $this->masker()->mask(
            'select * from `cards` where `card_number` in (?, ?)',
            ['4111111111111111', '4222222222222222']
        );

        $this->assertSame(['***MASKED***', '***MASKED***'], $result);
    }

    public function test_like_value_on_normal_column_is_not_masked(): void
    {
        $result = $this->masker()->mask('select * from `users` where `name` like ?', ['%john']);
        $this->assertSame(['%john'], $result);
    }

    public function test_no_patterns_configured_means_nothing_is_masked(): void
    {
        $masker = new SensitiveDataMasker([]);
        $result = $masker->mask('select * from `users` where `password` = ?', ['secret']);

        $this->assertSame(['secret'], $result, 'An empty pattern list must be a safe no-op, not an error.');
    }

    public function test_empty_bindings_returns_empty_array(): void
    {
        $result = $this->masker()->mask('select * from `users`', []);
        $this->assertSame([], $result);
    }
}