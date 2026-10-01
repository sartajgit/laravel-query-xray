<?php

namespace Sartajgit\QueryXray\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Sartajgit\QueryXray\QueryXrayServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [QueryXrayServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('query-xray.enabled', true);
        $app['config']->set('query-xray.environments', ['testing']);
        $app['config']->set('query-xray.auto_migrate', true);
    }
}