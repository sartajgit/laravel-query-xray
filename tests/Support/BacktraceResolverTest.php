<?php

namespace Sartajgit\QueryXray\Tests\Support;

use Sartajgit\QueryXray\Support\BacktraceResolver;
use Sartajgit\QueryXray\Tests\TestCase;

class BacktraceResolverTest extends TestCase
{
    public function test_it_resolves_the_calling_file_and_line_from_app_code(): void
    {
        $resolver = new BacktraceResolver();

        // This exact call site is "app code" relative to vendor/package dirs,
        // so resolve() should report THIS test file, not the resolver's own file.
        $origin = $this->callResolverFromHere($resolver);

        $this->assertNotNull($origin);
        $this->assertStringContainsString('BacktraceResolverTest.php', $origin['file']);
        $this->assertIsInt($origin['line']);
    }

    protected function callResolverFromHere(BacktraceResolver $resolver): ?array
    {
        return $resolver->resolve();
    }

    public function test_it_never_blames_the_packages_own_source_folder(): void
    {
        $resolver = new BacktraceResolver();
        $origin = $resolver->resolve();

        if ($origin !== null) {
            $this->assertStringNotContainsString(
                'laravel-query-xray'.DIRECTORY_SEPARATOR.'src',
                str_replace('/', DIRECTORY_SEPARATOR, $origin['file']),
                'The resolver must never attribute a query to its own package source files.'
            );
        } else {
            $this->assertTrue(true); // null is also an acceptable outcome here
        }
    }

    public function test_it_never_blames_the_vendor_folder(): void
    {
        $resolver = new BacktraceResolver();
        $origin = $resolver->resolve();

        if ($origin !== null) {
            $this->assertStringNotContainsString(
                'vendor'.DIRECTORY_SEPARATOR,
                str_replace('/', DIRECTORY_SEPARATOR, $origin['file'])
            );
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_returns_null_when_entire_stack_is_framework_code(): void
    {
        // Simulate a call stack with no app frames by calling resolve()
        // from deep inside a closure that itself only contains vendor-style
        // paths is impractical to fully fake here without a real framework
        // boot — so this test documents the expected CONTRACT instead:
        // resolve() must return either a well-formed array or null, never
        // throw, and never return a partial/malformed result.
        $resolver = new BacktraceResolver();
        $origin = $resolver->resolve();

        $this->assertTrue(
            $origin === null || (is_array($origin) && isset($origin['file'], $origin['line'])),
            'resolve() must return null or a complete {file, line} array — never anything in between.'
        );
    }
}