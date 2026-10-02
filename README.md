# Laravel Query X-Ray

Real-time N+1, slow query, duplicate query, unoptimized query, and missing-index detection for Laravel — with a live dashboard that shows exactly which file and line number each issue came from, plus a suggested fix.

Install it, enable it, browse your app normally, and watch the dashboard fill up with real problems from your real code.

## Features

- **N+1 detection** — same query shape firing repeatedly from the same line with different values
- **Slow query detection** — flags queries over a configurable threshold
- **Duplicate query detection** — the exact same query with the exact same values running more than once in a request
- **Unoptimized query patterns** — `SELECT *`, leading-wildcard `LIKE '%value'`, missing `LIMIT`
- **Missing index detection** — runs `EXPLAIN` on already-flagged queries and suggests an index (MySQL/MariaDB only)
- **Sensitive data masking** — `password`, `token`, and similar column values are masked before ever being stored
- **File + line tracking** — every finding points to the exact line in your app code
- **Live dashboard** — auto-refreshing, with time-range filtering, search, and per-category or global clear
- **Zero manual setup** — table auto-creates on first boot
- **Safe by default** — only runs in `local`/`staging` unless configured otherwise
- **Automated test suite + CI** — 48+ tests, verified against PHP 8.0–8.3 and Laravel 8–13 on every commit

## Requirements

- PHP 7.4 – 8.3
- Laravel 8.x – 13.x

## Installation

```bash
composer require sartajgit/laravel-query-xray --dev
```

Add to your `.env`:

QUERY_XRAY_ENABLED=true


Visit `/query-xray` after browsing a few pages.

## All configuration (`.env`) options

Publish the config file if you want to edit these as PHP instead of `.env`:
```bash
php artisan vendor:publish --tag=query-xray-config
```

| `.env` key | Default | What it does |
|---|---|---|
| `QUERY_XRAY_ENABLED` | `false` | Master switch. Nothing runs unless `true`. |
| `QUERY_XRAY_SLOW_MS` | `100` | Queries at/above this many ms are flagged `slow_query`. |
| `QUERY_XRAY_N_PLUS_ONE` | `3` | Minimum repeat count (same line, different values) to flag as `n_plus_one`. |
| `QUERY_XRAY_TABLE` | `query_xray_findings` | Table name used to store findings. |
| `QUERY_XRAY_AUTO_MIGRATE` | `true` | Auto-creates the findings table on first boot. Set `false` to run `php artisan migrate` yourself. |
| `QUERY_XRAY_RETENTION_DAYS` | `7` | How many days of findings `query-xray:prune` keeps. Older rows are deleted. Auto-scheduled daily — requires your app's scheduler (`php artisan schedule:run` on cron) to actually be running. |
| `QUERY_XRAY_MISSING_INDEX` | `false` | Enables `EXPLAIN`-based missing-index detection. Off by default — it's real extra DB load, turn on deliberately. |
| `QUERY_XRAY_MISSING_INDEX_MIN_ROWS` | `50` | Minimum rows scanned before a missing-index finding is reported (avoids flagging tiny tables). |
| `QUERY_XRAY_DASHBOARD` | `true` | Toggles the dashboard route on/off entirely. |
| `QUERY_XRAY_DASHBOARD_PATH` | `query-xray` | URL path the dashboard is served at. |
| `QUERY_XRAY_MIDDLEWARE` | `web` | Comma-separated middleware list for the dashboard routes. **See "Restricting dashboard access" below — the default has no login requirement.** |
| `QUERY_XRAY_POLL_SECONDS` | `5` | Default auto-refresh interval shown in the dashboard dropdown. |
| `QUERY_XRAY_TOP_N` | `5` | How many of the worst issues to show per category. |

## Restricting dashboard access

**By default, the dashboard has NO login requirement** — anyone who can reach the URL can view query data (including real SQL and file paths) and permanently delete it via the Clear buttons. This is intentional for quick local development; restrict it before deploying anywhere reachable by anyone else.

**Recommended — graceful login check (works even if your app has no `login` named route):**

QUERY_XRAY_MIDDLEWARE=web,query-xray.auth

This uses the package's own middleware: if the user is logged in, they proceed; if not, they're redirected to your app's `login` route if one exists, or shown a plain "please log in" page if it doesn't — never a crash.

**Alternative — Laravel's built-in `auth` middleware** (only use this if you're certain a route named `login` exists in your app, since it will throw `RouteNotFoundException` otherwise):

QUERY_XRAY_MIDDLEWARE=web,auth


**For tighter control (specific admins only, not every logged-in user):**
1. Publish the config (see above).
2. Define a Gate in your app, e.g. in `AuthServiceProvider`:
```php
   Gate::define('view-query-xray', fn ($user) => $user->is_admin);
```
3. In `config/query-xray.php`:
```php
   'middleware' => ['web', 'auth', 'can:view-query-xray'],
```

## Keeping the findings table from growing forever

```bash
php artisan query-xray:prune              # uses QUERY_XRAY_RETENTION_DAYS
php artisan query-xray:prune --days=3     # one-off override
```

This is auto-registered on your app's scheduler to run daily — but only takes effect if your app's scheduler is actually running (a cron entry calling `php artisan schedule:run` every minute, which is standard for any Laravel app using `Schedule::command(...)` features). If you've never set that up, add the standard cron entry per Laravel's own docs, or run the prune command manually/via your own cron.

## How it works

Every query fires through Laravel's `DB::listen()`. The package captures SQL, bindings, timing, and — via a filtered call-stack walk — the exact file and line in your own app code that triggered it. After each request finishes (via `terminating()`, adding no latency), findings are masked for sensitive data and written to the database. The dashboard reads from that table and polls for updates.

## What gets flagged and why

| Type | Trigger | Why it matters |
|---|---|---|
| `n_plus_one` | Same shape, same file/line, ≥3× with different values | Usually a missing `->with()` eager load in a loop |
| `slow_query` | Execution time ≥ threshold | Missing index, large scan, or inefficient join |
| `duplicate_query` | Same query, same values, run more than once | Wasted round-trip — cache the result |
| `unoptimized_query` (`select_star`) | `SELECT *` | Unnecessary columns, more memory/network cost |
| `unoptimized_query` (`leading_wildcard_like`) | `LIKE '%value'` | Can't use a standard index, forces a full scan |
| `unoptimized_query` (`missing_limit`) | Full-table `SELECT` with no `WHERE`/`LIMIT` | Loads the entire table as it grows |
| `missing_index` | `EXPLAIN` shows no index used on an already-flagged query | Suggests an `ALTER TABLE ... ADD INDEX` |

## Uninstalling

```bash
composer remove sartajgit/laravel-query-xray
```
```bash
php artisan tinker --execute="Illuminate\Support\Facades\Schema::dropIfExists('query_xray_findings');"
```

## Contributing

Issues and PRs welcome at [github.com/sartajgit/laravel-query-xray](https://github.com/sartajgit/laravel-query-xray). CI runs the full test suite against PHP 8.0–8.3 and Laravel 8–13 on every push.

## License

MIT — see [LICENSE](LICENSE).