<?php

namespace Sartajgit\QueryXray\Tests\Support;

use Sartajgit\QueryXray\Support\BacktraceResolver;
use Sartajgit\QueryXray\Tests\TestCase;

class BacktraceResolverBladeTest extends TestCase
{
    public function test_resolves_compiled_view_back_to_original_blade_source(): void
    {
        $viewsDir = $this->app->basePath('resources/views');
        @mkdir($viewsDir, 0777, true);
        $bladeSource = $viewsDir.'/xray-test-view.blade.php';
        file_put_contents($bladeSource, '{{ 1 }}');

        $compiledDir = storage_path('framework/views');
        @mkdir($compiledDir, 0777, true);
        $compiledFile = $compiledDir.'/xray-test-compiled.php';

        file_put_contents(
            $compiledFile,
            '<?php $origin = $resolver->resolve(); ?>'.
            '<?php /**PATH '.$bladeSource.' ENDPATH**/ ?>'
        );

        $resolver = new BacktraceResolver();
        $origin = null;

        $invoke = function () use ($resolver, $compiledFile, &$origin) {
            include $compiledFile;
        };
        $invoke();

        @unlink($bladeSource);
        @unlink($compiledFile);

        $this->assertNotNull($origin);
        $this->assertStringContainsString('xray-test-view.blade.php', $origin['file']);
        $this->assertStringNotContainsString('framework/views', $origin['file']);
    }
}