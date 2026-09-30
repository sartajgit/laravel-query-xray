# Laravel Query X-Ray

Real-time N+1, slow query, duplicate query, and unoptimized query detection for Laravel — with a live dashboard that shows exactly which file and line number each issue came from, plus a suggested fix.

Install it, enable it, browse your app normally, and watch the dashboard fill up with real problems from your real code — no manual test routes, no separate profiling tool to learn.

## Features

- **N+1 detection** — catches the same query shape firing repeatedly from the same line with different values (the classic missing-eager-load problem)
- **Slow query detection** — flags any query over a configurable threshold (default 100ms)
- **Duplicate query detection** — catches the exact same query with the exact same values running more than once in a single request
- **Unoptimized query patterns** — `SELECT *`, leading-wildcard `LIKE '%value'`, missing `LIMIT` on full-table scans
- **File + line tracking** — every finding points to the exact line in your app code that triggered it
- **Live dashboard** — auto-refreshing every few seconds, no manual reload needed
- **Light/dark theme** — remembers your preference
- **Zero manual setup** — table auto-creates on first boot, no `php artisan migrate` required
- **Safe by default** — only runs in `local`/`staging` environments unless you explicitly configure otherwise

## Requirements

- PHP 7.4 – 8.3
- Laravel 8.x – 13.x

## Installation

```bash
composer require sartajgit/laravel-query-xray --dev
```

Add to your `.env`:

QUERY_XRAY_ENABLED=true

That's it. Visit any page in your app once, then open: http://your-app.test/query-xray


The dashboard will show findings from every request your app handles from that point forward.

## Configuration

The package works with zero configuration out of the box. To customize it, publish the config file:

```bash
php artisan vendor:publish --tag=query-xray-config
```

This creates `config/query-xray.php`:

```php
return [
    'enabled' => env('QUERY_XRAY_ENABLED', false),

    'environments' => ['local', 'staging'],

    'slow_threshold_ms' => env('QUERY_XRAY_SLOW_MS', 100),

    'n_plus_one_threshold' => env('QUERY_XRAY_N_PLUS_ONE', 3),

    'table_name' => env('QUERY_XRAY_TABLE', 'query_xray_findings'),

    'auto_migrate' => env('QUERY_XRAY_AUTO_MIGRATE', true),

    'dashboard' => [
        'enabled' => env('QUERY_XRAY_DASHBOARD', true),
        'path' => env('QUERY_XRAY_DASHBOARD_PATH', 'query-xray'),
        'middleware' => ['web'],
        'poll_seconds' => env('QUERY_XRAY_POLL_SECONDS', 5),
        'top_n' => env('QUERY_XRAY_TOP_N', 5),
    ],
];
```

### Available `.env` options

| Key | Default | Description |
|---|---|---|
| `QUERY_XRAY_ENABLED` | `false` | Master switch. Query capture and the dashboard are both off unless this is `true`. |
| `QUERY_XRAY_SLOW_MS` | `100` | Queries at or above this many milliseconds are flagged as slow. |
| `QUERY_XRAY_N_PLUS_ONE` | `3` | Minimum repeat count from the same line to flag as N+1. |
| `QUERY_XRAY_TABLE` | `query_xray_findings` | Table name used to store findings. |
| `QUERY_XRAY_AUTO_MIGRATE` | `true` | Auto-creates the findings table on first boot. Set `false` to run `php artisan migrate` yourself. |
| `QUERY_XRAY_DASHBOARD` | `true` | Toggles the dashboard route on/off. |
| `QUERY_XRAY_DASHBOARD_PATH` | `query-xray` | The URL path the dashboard is served at. |
| `QUERY_XRAY_POLL_SECONDS` | `5` | How often the dashboard polls for new data, in seconds. |
| `QUERY_XRAY_TOP_N` | `5` | How many of the worst issues to show per category. |

## Restricting dashboard access

By default the dashboard route only uses the `web` middleware group and is only registered in `local`/`staging` environments. If your staging server is internet-facing, add authentication:

```php
'dashboard' => [
    'middleware' => ['web', 'auth'],
    // ...
],
```

## How it works

Every query your app runs fires through Laravel's `DB::listen()` event. The package captures each query's SQL, bindings, execution time, and — by walking the call stack — the exact file and line in your own application code that triggered it (framework and vendor code is automatically filtered out).

After each request finishes (using Laravel's `terminating()` hook, so this adds no latency to the response the user sees), findings are written to the database. The dashboard reads from that table and polls for updates every few seconds.

## What gets flagged and why

| Type | Trigger | Why it matters |
|---|---|---|
| `n_plus_one` | Same query shape, same file/line, ≥3 times with different values | Usually means a missing `->with()` eager load inside a loop |
| `slow_query` | Execution time ≥ threshold | Missing index, large table scan, or inefficient join |
| `duplicate_query` | Same query, same values, run more than once | Wasted round-trip — the result should be cached in a variable |
| `unoptimized_query` (`select_star`) | `SELECT *` | Pulls unnecessary columns, increasing memory and network cost |
| `unoptimized_query` (`leading_wildcard_like`) | `LIKE '%value'` | Cannot use a standard index, forces a full table scan |
| `unoptimized_query` (`missing_limit`) | Full-table `SELECT` with no `WHERE`/`LIMIT` | Will load the entire table into memory as it grows |

## Uninstalling

```bash
composer remove sartajgit/laravel-query-xray
```

Then drop the findings table if you no longer need the historical data:

```bash
php artisan tinker --execute="Illuminate\Support\Facades\Schema::dropIfExists('query_xray_findings');"
```

## Contributing

Issues and pull requests are welcome at [github.com/sartajgit/laravel-query-xray](https://github.com/sartajgit/laravel-query-xray).

## License

MIT — see [LICENSE](LICENSE).

## Support

If this package saved you some debugging time, consider [buying me a coffee](https://buymeacoffee.com/sartajgit). ☕