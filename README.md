# Laravel Query X-Ray

[![tests](https://github.com/sartajgit/laravel-query-xray/actions/workflows/tests.yml/badge.svg)](https://github.com/sartajgit/laravel-query-xray/actions/workflows/tests.yml)

**Status: v1.0.0-beta** — core detection, dashboard, and masking are tested against real production data and 60+ automated tests across PHP 8.0–8.3 and Laravel 8–13.

Real-time N+1, slow query, duplicate query, unoptimized query, and missing-index detection for Laravel — with a live dashboard that shows exactly which file and line number each issue came from, plus a suggested fix.

Install it, enable it, browse your app normally, and watch the dashboard fill up with real problems from your real code.

## Features

- **N+1 detection** — same query shape firing repeatedly from the same line with different values
- **Slow query detection** — flags queries over a configurable threshold
- **Duplicate query detection** — the exact same query with the exact same values running more than once in a request
- **Unoptimized query patterns** — `SELECT *`, leading-wildcard `LIKE '%value'`, missing `LIMIT`
- **Missing index detection** — runs `EXPLAIN` on already-flagged queries and suggests an index
- **Sensitive data masking** — column-name pattern matching (`password`, `token`, etc.) plus exact `table.column` overrides for generically-named sensitive columns
- **File + line tracking** — every finding points to the exact line in your app code, including inside Blade views
- **Live dashboard** — single-row stat tiles, auto-refreshing, with time-range filtering, search, and per-category or global clear
- **Storage growth warning** — a visible banner once stored findings exceed a configurable threshold, so unattended growth doesn't go unnoticed
- **Zero manual setup** — table auto-creates on first boot
- **Safe by default** — only runs in `local`/`staging` unless configured otherwise
- **Automated test suite + CI** — 60+ tests, verified against PHP 8.0–8.3 and Laravel 8–13 on every commit

## Requirements

- PHP 8.0 – 8.3
- Laravel 8.x – 13.x

## Installation

```bash
composer require sartajgit/laravel-query-xray:v1.0.0-beta --dev
```

Add to your `.env`:

QUERY_XRAY_ENABLED=true

Visit `/query-xray` after browsing a few pages.

## All configuration options

Most settings are controlled via `.env`. A few (sensitive-data pattern lists) are arrays and must be edited in the published config file instead. Publish it with:

```bash
php artisan vendor:publish --tag=query-xray-config
```

### `.env` options

| `.env` key | Default | What it does |
|---|---|---|
| `QUERY_XRAY_ENABLED` | `false` | Master switch. Nothing runs unless `true`. |
| `QUERY_XRAY_SLOW_MS` | `100` | Queries at/above this many ms are flagged `slow_query`. |
| `QUERY_XRAY_N_PLUS_ONE` | `3` | Minimum repeat count (same line, different values) to flag as `n_plus_one`. |
| `QUERY_XRAY_TABLE` | `query_xray_findings` | Table name used to store findings. |
| `QUERY_XRAY_AUTO_MIGRATE` | `true` | Auto-creates the findings table on first boot. Set `false` to run `php artisan migrate` yourself. |
| `QUERY_XRAY_RETENTION_DAYS` | `7` | How many days of findings `query-xray:prune` keeps. Older rows are deleted. Auto-registered on your app's scheduler daily — requires your app's scheduler (`php artisan schedule:run` on cron) to actually be running; see "Keeping the findings table from growing forever" below. |
| `QUERY_XRAY_WARNING_THRESHOLD` | `1000` | Dashboard shows a warning banner once total stored findings exceed this number — an early signal that pruning may not be running. |
| `QUERY_XRAY_MISSING_INDEX` | `false` | Enables `EXPLAIN`-based missing-index detection. Off by default — it's real extra DB load, turn on deliberately. |
| `QUERY_XRAY_MISSING_INDEX_MIN_ROWS` | `50` | Minimum rows scanned before a missing-index finding is reported (avoids flagging tiny tables). |
| `QUERY_XRAY_DASHBOARD` | `true` | Toggles the dashboard route on/off entirely. |
| `QUERY_XRAY_DASHBOARD_PATH` | `query-xray` | URL path the dashboard is served at. |
| `QUERY_XRAY_MIDDLEWARE` | `web` | Comma-separated middleware list for the dashboard routes. **See "Restricting dashboard access" below — the default has no login requirement.** |
| `QUERY_XRAY_POLL_SECONDS` | `5` | Initial auto-refresh interval selected in the dashboard dropdown (also selectable live: 30s / 1min / 2min / 5min / 10min). |
| `QUERY_XRAY_TOP_N` | `5` | How many of the worst issues to show per category. |

### Config-file-only options (arrays — publish the config to edit)

| Key | Default | What it does |
|---|---|---|
| `sensitive_patterns` | `password`, `token`, `secret`, `api_key`, `credit_card`, `cvv`, `ssn`, `otp`, and similar | Column-name substrings that trigger masking of that column's value, regardless of exact name. |
| `sensitive_columns` | `['sessions.id']` | Exact `table.column` pairs to mask even when the column name itself doesn't look sensitive (e.g. Laravel's own session ID, stored under the generic name `id`). Add your own app-specific ones here. |

## Restricting dashboard access

**By default, the dashboard has NO login requirement** — anyone who can reach the URL can view query data (including real SQL and file paths) and permanently delete it via the Clear buttons. This is intentional for quick local development; restrict it before deploying anywhere reachable by anyone else.

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
   'middleware' => ['web', 'auth'],
```

## Keeping the findings table from growing forever

```bash
php artisan query-xray:prune              # uses QUERY_XRAY_RETENTION_DAYS
php artisan query-xray:prune --days=3     # one-off override
```

This is auto-registered on your app's scheduler to run daily — but only takes effect if your app's scheduler is actually running (a cron entry calling `php artisan schedule:run` every minute, which is standard for any Laravel app using `Schedule::command(...)` features). If you've never set that up, add the standard cron entry per Laravel's own docs, or run the prune command manually/via your own cron. The dashboard also shows a warning banner (`QUERY_XRAY_WARNING_THRESHOLD`) if stored findings grow past a configurable number, as a visible signal pruning may not be active.

## How it works

Every query fires through Laravel's `DB::listen()`. The package captures SQL, bindings, timing, and — via a filtered call-stack walk — the exact file (and, for non-Blade code, line) in your own application code that triggered it, with framework/vendor noise filtered out. Queries triggered from inside a Blade view are resolved back to the real `.blade.php` source file rather than Laravel's compiled cache path. After each request finishes (via `terminating()`, adding no latency to the response), findings are masked for sensitive data and written to the database. The dashboard reads from that table and polls for updates on a configurable interval.

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

## Known limitations

- **Missing-index detection is MySQL/MariaDB-only**, and uses regex-based column guessing rather than full SQL parsing — works well for the simple queries this tool targets, but complex joins/subqueries may produce imperfect suggestions.
- **Blade-sourced queries report the correct source file but no line number** (shown as line `0`) — Blade compiles directives into multiple lines of generated PHP with no reliable 1:1 mapping back to the original file, so no line is reported rather than risking a confidently wrong one.
- **Sensitive data masking is pattern/exact-column based, not exhaustive** — a sensitive value under an unanticipated column name on your own schema won't be caught automatically; add it to `sensitive_columns` in the published config.
- **Automatic retention pruning depends entirely on your app's scheduler actually running** (real cron calling `schedule:run`) — this has been verified to register correctly and to work when triggered manually, but has not yet been observed running unattended over real elapsed time.

## Uninstalling

```bash
composer remove sartajgit/laravel-query-xray
```
```bash
php artisan tinker --execute="Illuminate\Support\Facades\Schema::dropIfExists('query_xray_findings');"
```

## Contributing

Issues and PRs welcome at [github.com/sartajgit/laravel-query-xray](https://github.com/sartajgit/laravel-query-xray). CI runs the full test suite against PHP 8.0–8.3 and Laravel 8–13 on every push.

## Support

If this package saved you some debugging time, consider [buying me a coffee](https://buymeacoffee.com/wHXVvzP). ☕

## License

MIT — see [LICENSE](LICENSE).
