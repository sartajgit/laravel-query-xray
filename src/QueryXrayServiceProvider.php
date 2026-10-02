<?php

namespace Sartajgit\QueryXray;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Sartajgit\QueryXray\Collectors\QueryCollector;
use Sartajgit\QueryXray\Console\Commands\PruneCommand;

class QueryXrayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/query-xray.php', 'query-xray');

        $this->app->singleton(QueryCollector::class, fn () => new QueryCollector());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/query-xray.php' => config_path('query-xray.php'),
        ], 'query-xray-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'query-xray');

        $this->app['router']->aliasMiddleware('query-xray.auth', \Sartajgit\QueryXray\Http\Middleware\EnsureQueryXrayAccess::class);

        if (config('query-xray.dashboard.enabled', true) && $this->allowedEnvironment()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if (! $this->shouldRun()) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class]);
        }

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('query-xray:prune')->daily();
        });

        $this->ensureTableExists();

        DB::listen(function ($query) {
            $this->app->make(QueryCollector::class)->record($query);
        });

        $this->app->terminating(function () {
            $this->app->make(QueryCollector::class)->persist();
        });
    }

    protected function shouldRun(): bool
    {
        return config('query-xray.enabled') && $this->allowedEnvironment();
    }

    protected function allowedEnvironment(): bool
    {
        return in_array($this->app->environment(), config('query-xray.environments', ['local']));
    }

    protected function ensureTableExists(): void
    {
        if (! config('query-xray.auto_migrate', true)) {
            return;
        }

        $cacheKey = 'query-xray:table-checked';

        if (Cache::get($cacheKey)) {
            return;
        }

        $table = config('query-xray.table_name', 'query_xray_findings');

        if (! Schema::hasTable($table)) {
            Artisan::call('migrate', [
                '--path' => __DIR__.'/../database/migrations',
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        Cache::put($cacheKey, true, now()->addMinutes(10));
    }
}