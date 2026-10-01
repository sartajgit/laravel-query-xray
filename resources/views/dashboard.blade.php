<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Query X-Ray Dashboard</title>
    <style>
        * { box-sizing: border-box; }

        :root {
            --bg: #0f1115; --card-bg: #1a1d24; --border: #2a2e37; --row-border: #1e2128;
            --row-hover: #171a20; --text: #e6e6e6; --text-dim: #8a8f98; --text-faint: #565b66;
            --sql-color: #d1d5db; --loc-color: #74c0fc; --pill-bg: #2a2e37;
            --n1-color: #ff6b6b; --slow-color: #ffa94d; --dup-color: #ffd43b; --unopt-color: #4dabf7;
            --danger: #ff6b6b; --danger-hover: #ff8787; --input-bg: #14161a;
        }

        html[data-theme="light"] {
            --bg: #f5f6f8; --card-bg: #ffffff; --border: #e1e4e9; --row-border: #edeff2;
            --row-hover: #f7f8fa; --text: #1a1d24; --text-dim: #5c6270; --text-faint: #9a9fa8;
            --sql-color: #2a2e37; --loc-color: #1c7ed6; --pill-bg: #eef0f3;
            --n1-color: #e03131; --slow-color: #e8590c; --dup-color: #f08c00; --unopt-color: #1971c2;
            --danger: #e03131; --danger-hover: #c92a2a; --input-bg: #ffffff;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg); color: var(--text); margin: 0; padding: 24px;
            transition: background .15s ease, color .15s ease;
        }

        .header-row { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: var(--text-dim); font-size: 13px; }
        .header-actions { display: flex; gap: 8px; align-items: center; }

        .btn {
            border: 1px solid var(--border); background: var(--card-bg); color: var(--text);
            border-radius: 8px; padding: 6px 14px; font-size: 13px; cursor: pointer;
        }
        .btn:hover { border-color: var(--loc-color); }
        .btn-danger { color: var(--danger); border-color: var(--danger); }
        .btn-danger:hover { background: var(--danger); color: #fff; }
        .btn-clear-small {
            font-size: 11px; padding: 2px 10px; border-radius: 10px;
            border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer;
        }
        .btn-clear-small:hover { color: var(--danger); border-color: var(--danger); }

        .toolbar {
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            margin: 20px 0; padding: 14px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px;
        }
        select, input[type="text"], input[type="datetime-local"] {
            background: var(--input-bg); color: var(--text); border: 1px solid var(--border);
            border-radius: 6px; padding: 6px 10px; font-size: 13px;
        }
        .custom-range { display: none; gap: 8px; align-items: center; }
        .custom-range.active { display: flex; }
        .toolbar label { font-size: 12px; color: var(--text-dim); }
        #refresh-indicator { font-size: 12px; color: var(--text-dim); display: none; white-space: nowrap; }

        .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 32px; }
        .stat-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center; }
        .stat-card .num { font-size: 28px; font-weight: 700; }
        .stat-card .label { font-size: 12px; color: var(--text-dim); margin-top: 4px; text-transform: uppercase; letter-spacing: .04em; }
        .stat-card.total .num { color: var(--text); }
        .stat-card.n_plus_one .num { color: var(--n1-color); }
        .stat-card.slow_query .num { color: var(--slow-color); }
        .stat-card.duplicate_query .num { color: var(--dup-color); }
        .stat-card.unoptimized_query .num { color: var(--unopt-color); }

        .section { margin-bottom: 32px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .section h2 { font-size: 15px; margin: 0; display: flex; align-items: center; gap: 8px; }
        .badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--pill-bg); color: var(--text-dim); }

        table { width: 100%; border-collapse: collapse; font-size: 13px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        th { text-align: left; color: var(--text-dim); font-weight: 500; padding: 8px 10px; border-bottom: 1px solid var(--border); }
        td { padding: 8px 10px; border-bottom: 1px solid var(--row-border); vertical-align: top; }
        tr:hover td { background: var(--row-hover); }

        .sql-wrap { max-width: 100%; }
        .sql {
            font-family: ui-monospace, "SF Mono", Menlo, monospace; color: var(--sql-color);
            white-space: pre-wrap; word-break: break-word;
            max-height: 4.2em;
            line-height: 1.4em;
            overflow: hidden;
        }
        .sql.expanded { max-height: none; }
        .see-more {
            font-size: 11px; color: var(--loc-color); cursor: pointer;
            display: none; margin-top: 4px; user-select: none;
        }
        .see-more.visible { display: inline-block; }

        .loc { font-family: ui-monospace, monospace; color: var(--loc-color); font-size: 12px; white-space: nowrap; font-weight: 600; }
        .seen-pill { background: var(--pill-bg); padding: 2px 8px; border-radius: 10px; font-size: 12px; }
        .last-seen { color: var(--text-faint); font-size: 11px; display: block; margin-top: 2px; }
        .occurred-at { color: var(--text-faint); font-size: 11px; display: block; margin-top: 1px; }
        .suggestion { color: var(--text-dim); font-size: 12px; max-width: 320px; }
        .empty { color: var(--text-faint); font-style: italic; padding: 16px 10px; }
    </style>
</head>
<body>

    <div class="header-row">
        <div>
            <h1>Query X-Ray</h1>
            <div class="subtitle">Last updated: <span id="generated-at">{{ $generated_at }}</span></div>
        </div>
        <div class="header-actions">
            <button class="btn" id="theme-toggle" type="button"><span id="theme-icon">🌙</span> <span id="theme-label">Dark</span></button>
            <button class="btn btn-danger" id="clear-all" type="button">🗑 Clear All</button>
        </div>
    </div>

    <div class="toolbar">
        <label for="range-select">Time range:</label>
        <select id="range-select">
            <option value="1h">Last 1 hour</option>
            <option value="3h">Last 3 hours</option>
            <option value="24h" selected>Last 24 hours</option>
            <option value="3d">Last 3 days</option>
            <option value="custom">Custom range…</option>
            <option value="all">All time</option>
        </select>

        <div class="custom-range" id="custom-range">
            <label>From <input type="datetime-local" id="from-input"></label>
            <label>To <input type="datetime-local" id="to-input"></label>
            <button class="btn" id="apply-custom" type="button">Apply</button>
        </div>

        <label for="poll-select">Auto-refresh:</label>
        <select id="poll-select">
            <option value="30000">Every 30s</option>
            <option value="60000">Every 1 min</option>
            <option value="120000">Every 2 min</option>
            <option value="300000" selected>Every 5 min</option>
            <option value="600000">Every 10 min</option>
        </select>
        <span id="refresh-indicator">⏳ Refreshing…</span>

        <label for="search-input" style="margin-left: auto;">Search:</label>
        <input type="text" id="search-input" placeholder="query text or file path…" style="width: 240px;">
    </div>

    <div class="stats">
        <div class="stat-card total"><div class="num" id="stat-total">{{ $stats['total'] }}</div><div class="label">Total Detections</div></div>
        <div class="stat-card n_plus_one"><div class="num" id="stat-n_plus_one">{{ $stats['n_plus_one'] }}</div><div class="label">N+1</div></div>
        <div class="stat-card slow_query"><div class="num" id="stat-slow_query">{{ $stats['slow_query'] }}</div><div class="label">Slow Queries</div></div>
        <div class="stat-card duplicate_query"><div class="num" id="stat-duplicate_query">{{ $stats['duplicate_query'] }}</div><div class="label">Duplicates</div></div>
        <div class="stat-card unoptimized_query"><div class="num" id="stat-unoptimized_query">{{ $stats['unoptimized_query'] }}</div><div class="label">Unoptimized</div></div>
    </div>

    @php
        $sections = [
            'n_plus_one' => 'Top N+1 Issues',
            'slow_query' => 'Top Slow Queries',
            'duplicate_query' => 'Top Duplicate Query Issues',
            'unoptimized_query' => 'Top Unoptimized Query Issues',
        ];
        $topN = config('query-xray.dashboard.top_n', 5);
    @endphp

    @foreach ($sections as $key => $title)
        <div class="section" id="section-{{ $key }}">
            <div class="section-header">
                <h2>{{ $title }} <span class="badge">top {{ $topN }}</span></h2>
                <button class="btn-clear-small" data-clear-type="{{ $key }}" type="button">Clear this category</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 38%">Query</th>
                        <th>Location (file:line)</th>
                        <th>Seen</th>
                        <th>Worst Time (ms)</th>
                        <th>Suggestion</th>
                    </tr>
                </thead>
                <tbody id="rows-{{ $key }}">
                    @forelse ($top[$key] as $i => $row)
                        <tr>
                            <td class="sql-wrap">
                                <div class="sql" id="sql-{{ $key }}-{{ $i }}">{{ $row->sql }}</div>
                                <span class="see-more" data-target="sql-{{ $key }}-{{ $i }}">See more</span>
                            </td>
                            <td class="loc">{{ $row->file ? $row->file.':'.$row->line : '— (framework internal)' }}</td>
                            <td>
                                <span class="seen-pill">{{ $row->occurrences }}×</span>
                                <span class="last-seen" data-iso="{{ $row->last_seen_iso }}">last: {{ \Illuminate\Support\Carbon::parse($row->last_seen, config('app.timezone'))->diffForHumans() }}</span>
                                <span class="occurred-at" data-iso="{{ $row->last_seen_iso }}">{{ \Illuminate\Support\Carbon::parse($row->last_seen, config('app.timezone'))->timezone(config('app.timezone'))->format('M j, Y g:i A T') }}</span>
                            </td>
                            <td>{{ $row->max_time_ms !== null ? number_format($row->max_time_ms, 2) : '—' }}</td>
                            <td class="suggestion">{{ $row->suggestion }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No findings in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const baseDataUrl = @json(route('query-xray.data'));
        const clearAllUrl = @json(route('query-xray.clear'));
        const clearTypeUrlTemplate = @json(route('query-xray.clear-type', ['type' => '__TYPE__']));

        // ---------- Theme ----------
        const THEME_KEY = 'query-xray-theme';
        const root = document.documentElement;
        const themeIcon = document.getElementById('theme-icon');
        const themeLabel = document.getElementById('theme-label');

        function applyTheme(theme) {
            root.setAttribute('data-theme', theme);
            themeIcon.textContent = theme === 'light' ? '☀️' : '🌙';
            themeLabel.textContent = theme === 'light' ? 'Light' : 'Dark';
        }
        applyTheme(localStorage.getItem(THEME_KEY) || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'));
        document.getElementById('theme-toggle').addEventListener('click', () => {
            const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            localStorage.setItem(THEME_KEY, next);
            applyTheme(next);
        });

        // ---------- Auto-refresh interval control ----------
        let pollMs = 300000; // 5 minutes
        let pollTimer = null;
        const pollSelect = document.getElementById('poll-select');
        const refreshIndicator = document.getElementById('refresh-indicator');

        pollSelect.addEventListener('change', () => {
            pollMs = parseInt(pollSelect.value, 10);
            restartPolling();
        });

        function restartPolling() {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = setInterval(refresh, pollMs);
        }

        // ---------- Filters state ----------
        let currentFilters = { range: '24h', from: '', to: '', search: '' };

        const rangeSelect = document.getElementById('range-select');
        const customRangeBox = document.getElementById('custom-range');
        const fromInput = document.getElementById('from-input');
        const toInput = document.getElementById('to-input');
        const searchInput = document.getElementById('search-input');

        rangeSelect.addEventListener('change', () => {
            currentFilters.range = rangeSelect.value;
            customRangeBox.classList.toggle('active', rangeSelect.value === 'custom');
            if (rangeSelect.value !== 'custom') refresh();
        });

        document.getElementById('apply-custom').addEventListener('click', () => {
            currentFilters.range = 'custom';
            currentFilters.from = fromInput.value ? fromInput.value.replace('T', ' ') + ':00' : '';
            currentFilters.to = toInput.value ? toInput.value.replace('T', ' ') + ':00' : '';
            refresh();
        });

        let searchDebounce;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                currentFilters.search = searchInput.value;
                refresh();
            }, 400);
        });

        function buildDataUrl() {
            const params = new URLSearchParams();
            params.set('range', currentFilters.range);
            if (currentFilters.range === 'custom') {
                if (currentFilters.from) params.set('from', currentFilters.from);
                if (currentFilters.to) params.set('to', currentFilters.to);
            }
            if (currentFilters.search) params.set('search', currentFilters.search);
            return baseDataUrl + '?' + params.toString();
        }

        // ---------- Clear actions ----------
        async function callDelete(url) {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            return res.ok;
        }

        document.getElementById('clear-all').addEventListener('click', async () => {
            if (!confirm('Clear ALL query findings? This cannot be undone.')) return;
            if (await callDelete(clearAllUrl)) refresh();
        });

        document.querySelectorAll('[data-clear-type]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const type = btn.getAttribute('data-clear-type');
                if (!confirm(`Clear all "${type}" findings? This cannot be undone.`)) return;
                const url = clearTypeUrlTemplate.replace('__TYPE__', type);
                if (await callDelete(url)) refresh();
            });
        });

        // ---------- "See more" toggle (delegated) ----------
        document.addEventListener('click', (e) => {
            if (!e.target.classList.contains('see-more')) return;
            const target = document.getElementById(e.target.getAttribute('data-target'));
            if (!target) return;
            const expanded = target.classList.toggle('expanded');
            e.target.textContent = expanded ? 'See less' : 'See more';
        });

        function updateSeeMoreVisibility(containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.querySelectorAll('.sql-wrap').forEach(wrap => {
                const sql = wrap.querySelector('.sql');
                const link = wrap.querySelector('.see-more');
                if (!sql || !link) return;
                sql.classList.remove('expanded');
                link.textContent = 'See more';
                link.classList.toggle('visible', sql.scrollHeight > sql.clientHeight + 2);
            });
        }

        const SECTION_IDS = ['section-n_plus_one', 'section-slow_query', 'section-duplicate_query', 'section-unoptimized_query'];
        SECTION_IDS.forEach(id => updateSeeMoreVisibility(id));

        // ---------- Date helpers (ALWAYS use the *_iso field — it is
        // explicit UTC, e.g. "2026-09-30T05:52:00+00:00" — never parse the
        // plain "Y-m-d H:i:s" string directly in JS, since the browser will
        // silently treat it as local time and produce a wrong "time ago") ----------
        function timeAgo(isoStr) {
            if (!isoStr) return '';
            const diff = (Date.now() - new Date(isoStr).getTime()) / 1000;
            if (diff < 60) return 'moments ago';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return Math.floor(diff / 86400) + 'd ago';
        }
        function formatDateTime(isoStr) {
            if (!isoStr) return '';
            const d = new Date(isoStr);
            return d.toLocaleString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit',
            });
        }

        // Re-render the initial Blade-rendered rows' relative time using the
        // browser's own local clock/timezone (nicer than the server-rendered
        // version, and keeps ticking correctly on every poll after this).
        document.querySelectorAll('.last-seen[data-iso]').forEach(el => {
            const iso = el.getAttribute('data-iso');
            if (iso) el.textContent = 'last: ' + timeAgo(iso);
        });
        document.querySelectorAll('.occurred-at[data-iso]').forEach(el => {
            const iso = el.getAttribute('data-iso');
            if (iso) el.textContent = formatDateTime(iso);
        });

        // ---------- Rendering + polling ----------
        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function renderRows(tbodyId, rows) {
            const tbody = document.getElementById(tbodyId);
            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="5" class="empty">No findings in this range.</td></tr>`;
                return;
            }
            tbody.innerHTML = rows.map((r, i) => {
                const sqlId = tbodyId + '-sql-' + i;
                return `
                <tr>
                    <td class="sql-wrap">
                        <div class="sql" id="${sqlId}">${escapeHtml(r.sql)}</div>
                        <span class="see-more" data-target="${sqlId}">See more</span>
                    </td>
                    <td class="loc">${r.file ? escapeHtml(r.file) + ':' + r.line : '— (framework internal)'}</td>
                    <td>
                        <span class="seen-pill">${r.occurrences}×</span>
                        <span class="last-seen">last: ${timeAgo(r.last_seen_iso)}</span>
                        <span class="occurred-at">${formatDateTime(r.last_seen_iso)}</span>
                    </td>
                    <td>${r.max_time_ms !== null ? Number(r.max_time_ms).toFixed(2) : '—'}</td>
                    <td class="suggestion">${escapeHtml(r.suggestion)}</td>
                </tr>`;
            }).join('');
        }

        async function refresh() {
            refreshIndicator.style.display = 'inline';
            try {
                const res = await fetch(buildDataUrl(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                document.getElementById('generated-at').textContent = data.generated_at;
                for (const key of ['total', 'n_plus_one', 'slow_query', 'duplicate_query', 'unoptimized_query']) {
                    document.getElementById('stat-' + key).textContent = data.stats[key];
                }
                renderRows('rows-n_plus_one', data.top.n_plus_one);
                renderRows('rows-slow_query', data.top.slow_query);
                renderRows('rows-duplicate_query', data.top.duplicate_query);
                renderRows('rows-unoptimized_query', data.top.unoptimized_query);

                SECTION_IDS.forEach(id => updateSeeMoreVisibility(id));
            } catch (e) {
                // Silently skip a failed poll — next interval will retry.
            } finally {
                refreshIndicator.style.display = 'none';
            }
        }

        restartPolling();
    </script>

</body>
</html>