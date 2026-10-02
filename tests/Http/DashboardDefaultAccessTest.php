<?php

namespace Sartajgit\QueryXray\Tests\Http;

use Sartajgit\QueryXray\Tests\TestCase;

class DashboardDefaultAccessTest extends TestCase
{
    public function test_default_middleware_allows_access_without_login(): void
    {
        // No override here — this boots with whatever
        // config/query-xray.php's real default is: ['web'] only.
        $response = $this->get('/query-xray');

        $response->assertStatus(200);
    }
}