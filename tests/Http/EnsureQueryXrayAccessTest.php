<?php

namespace Sartajgit\QueryXray\Tests\Http;

use Illuminate\Support\Facades\Route;
use Sartajgit\QueryXray\Tests\TestCase;

class EnsureQueryXrayAccessTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Force the dashboard route to require the custom auth middleware
        // for every test in this class, regardless of the package's default.
        $app['config']->set('query-xray.dashboard.middleware', ['web', 'query-xray.auth']);
    }

    public function test_unauthenticated_user_sees_graceful_message_when_no_login_route_exists(): void
    {
        // Deliberately do NOT register a "login" route — this simulates
        // your sandbox app's exact situation.
        $response = $this->get('/query-xray');

        $response->assertStatus(403);
        $response->assertSee('Login Required');
    }

    public function test_unauthenticated_user_is_redirected_when_login_route_exists(): void
    {
        Route::get('/fake-login', function () {
            return 'fake login page';
        })->name('login');

        $response = $this->get('/query-xray');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = new class extends \Illuminate\Foundation\Auth\User {
            protected $guarded = [];
        };
        $user->id = 1;

        $this->actingAs($user);

        $response = $this->get('/query-xray');

        $response->assertStatus(200);
    }
}