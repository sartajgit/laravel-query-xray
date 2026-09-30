<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Query X-Ray Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; }

        :root {
            --bg: #0f1115;
            --card-bg: #1a1d24;
            --border: #2a2e37;
            --row-border: #1e2128;
            --row-hover: #171a20;
            --text: #e6e6e6;
            --text-dim: #8a8f98;
            --text-faint: #565b66;
            --sql-color: #d1d5db;
            --loc-color: #74c0fc;
            --pill-bg: #2a2e37;
            --n1-color: #ff6b6b;
            --slow-color: #ffa94d;
            --dup-color: #ffd43b;
            --unopt-color: #4dabf7;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.2);
        }

        html[data-theme="light"] {
            --bg: #f5f6f8;
            --card-bg: #ffffff;
            --border: #e1e4e9;
            --row-border: #edeff2;
            --row-hover: #f7f8fa;
            --text: #1a1d24;
            --text-dim: #5c6270;
            --text-faint: #9a9fa8;
            --sql-color: #2a2e37;
            --loc-color: #1c7ed6;
            --pill-bg: #eef0f3;
            --n1-color: #e03131;
            --slow-color: #e8590c;
            --dup-color: #f08c00;
            --unopt-color: #1971c2;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 24px 20px;
            transition: background .2s ease, color .2s ease;
            line-height: 1.4;
        }

        .header-row { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        h1 { 
            font-size: 24px; 
            font-weight: 600;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        h1::before {
            content: "🔍";
            font-size: 22px;
            opacity: 0.8;
        }

        .subtitle { 
            color: var(--text-dim); 
            font-size: 13px; 
            line-height: 1.5;
            max-width: 700px;
        }

        .subtitle strong {
            color: var(--text);
            font-weight: 600;
        }

        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 8px 18px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            user-select: none;
            transition: border-color .15s, box-shadow .15s;
            box-shadow: var(--shadow-sm);
            white-space: nowrap;
        }
        .theme-toggle:hover { 
            border-color: var(--loc-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 28px;
        }
        @media (max-width: 700px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px 12px;
            text-align: center;
            transition: transform .1s, box-shadow .15s;
            box-shadow: var(--shadow-sm);
        }
        .stat-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .stat-card .num { 
            font-size: 28px; 
            font-weight: 700; 
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        .stat-card .label { 
            font-size: 11px; 
            color: var(--text-dim); 
            margin-top: 6px; 
            text-transform: uppercase; 
            letter-spacing: .05em;
            font-weight: 600;
        }

        .stat-card.total .num { color: var(--text); }
        .stat-card.n_plus_one .num { color: var(--n1-color); }
        .stat-card.slow_query .num { color: var(--slow-color); }
        .stat-card.duplicate_query .num { color: var(--dup-color); }
        .stat-card.unoptimized_query .num { color: var(--unopt-color); }

        .section { margin-bottom: 32px; }

        .section h2 { 
            font-size: 16px; 
            font-weight: 600;
            margin: 0 0 12px; 
            display: flex; 
            align-items: center; 
            gap: 10px;
            letter-spacing: -0.01em;
        }

        .badge { 
            font-size: 11px; 
            font-weight: 500;
            padding: 3px 10px; 
            border-radius: 12px; 
            background: var(--pill-bg); 
            color: var(--text-dim); 
            letter-spacing: 0.01em;
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 13px; 
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-radius: 10px; 
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        th { 
            text-align: left; 
            color: var(--text-dim); 
            font-weight: 600; 
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .03em;
            padding: 12px 14px; 
            border-bottom: 1px solid var(--border); 
            background: var(--card-bg);
        }

        td { 
            padding: 12px 14px; 
            border-bottom: 1px solid var(--row-border); 
            vertical-align: top; 
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--row-hover); }

        .sql { 
            font-family: ui-monospace, "SF Mono", "Cascadia Code", Menlo, monospace; 
            color: var(--sql-color); 
            white-space: pre-wrap; 
            word-break: break-word;
            font-size: 12.5px;
            line-height: 1.5;
        }

        .loc { 
            font-family: ui-monospace, monospace; 
            color: var(--loc-color); 
            font-size: 12px; 
            white-space: nowrap; 
            font-weight: 600;
        }

        .seen-pill { 
            background: var(--pill-bg); 
            padding: 3px 10px; 
            border-radius: 12px; 
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .last-seen { 
            color: var(--text-faint); 
            font-size: 11px; 
            display: block; 
            margin-top: 4px;
            font-weight: 400;
        }

        .suggestion { 
            color: var(--text-dim); 
            font-size: 12px; 
            max-width: 320px;
            line-height: 1.5;
        }

        .empty { 
            color: var(--text-faint); 
            font-style: italic; 
            padding: 24px 14px; 
            text-align: center;
            font-size: 13px;
        }

        /* subtle scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-faint); }
    </style>
    <script data-name="BMC-Widget" data-cfasync="false" src="https://cdnjs.buymeacoffee.com/1.0.0/widget.prod.min.js" data-id="wHXVvzP" data-description="Support me on Buy me a coffee!" data-message="" data-color="#BD5FFF" data-position="Right" data-x_margin="18" data-y_margin="18"></script>
</head>
<body>

    <div class="header-row">
        <div>
            <h1>Query X-Ray</h1>
            <div class="subtitle">
                Last updated: <span id="generated-at">{{ $generated_at }}</span> · Auto‑refresh every {{ config('query-xray.dashboard.poll_seconds', 5) }}s<br>
                <strong>Each row = one distinct issue</strong> — “Seen” counts occurrences.
            </div>
        </div>
        <button class="theme-toggle" id="theme-toggle" type="button">
            <span id="theme-icon">🌙</span>
            <span id="theme-label">Dark</span>
        </button>
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
            <h2>{{ $title }} <span class="badge">top {{ $topN }} distinct issues</span></h2>
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
                    @forelse ($top[$key] as $row)
                        <tr>
                            <td class="sql">{{ $row->sql }}</td>
                            <td class="loc">{{ $row->file ? $row->file.':'.$row->line : '— (framework internal)' }}</td>
                            <td>
                                <span class="seen-pill">{{ $row->occurrences }}×</span>
                                <span class="last-seen">last: {{ \Illuminate\Support\Carbon::parse($row->last_seen)->diffForHumans() }}</span>
                            </td>
                            <td>{{ $row->max_time_ms !== null ? number_format($row->max_time_ms, 2) : '—' }}</td>
                            <td class="suggestion">{{ $row->suggestion }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No {{ strtolower($title) }} found yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <script>
        // ---------- Theme toggle ----------
        const THEME_KEY = 'query-xray-theme';
        const root = document.documentElement;
        const toggleBtn = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const themeLabel = document.getElementById('theme-label');

        function applyTheme(theme) {
            root.setAttribute('data-theme', theme);
            themeIcon.textContent = theme === 'light' ? '☀️' : '🌙';
            themeLabel.textContent = theme === 'light' ? 'Light' : 'Dark';
        }

        function initialTheme() {
            const saved = localStorage.getItem(THEME_KEY);
            if (saved) return saved;
            return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        }

        applyTheme(initialTheme());

        toggleBtn.addEventListener('click', () => {
            const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            localStorage.setItem(THEME_KEY, next);
            applyTheme(next);
        });

        // ---------- Live polling ----------
        const dataUrl = @json(route('query-xray.data'));
        const pollMs = {{ (int) config('query-xray.dashboard.poll_seconds', 5) * 1000 }};

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function timeAgo(dateStr) {
            const diff = (Date.now() - new Date(dateStr.replace(' ', 'T'))) / 1000;
            if (diff < 60) return 'moments ago';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            return Math.floor(diff / 3600) + 'h ago';
        }

        function renderRows(tbodyId, rows, emptyLabel) {
            const tbody = document.getElementById(tbodyId);
            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="5" class="empty">No ${emptyLabel} found yet.</td></tr>`;
                return;
            }
            tbody.innerHTML = rows.map(r => `
                <tr>
                    <td class="sql">${escapeHtml(r.sql)}</td>
                    <td class="loc">${r.file ? escapeHtml(r.file) + ':' + r.line : '— (framework internal)'}</td>
                    <td>
                        <span class="seen-pill">${r.occurrences}×</span>
                        <span class="last-seen">last: ${timeAgo(r.last_seen)}</span>
                    </td>
                    <td>${r.max_time_ms !== null ? Number(r.max_time_ms).toFixed(2) : '—'}</td>
                    <td class="suggestion">${escapeHtml(r.suggestion)}</td>
                </tr>
            `).join('');
        }

        async function refresh() {
            try {
                const res = await fetch(dataUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                document.getElementById('generated-at').textContent = data.generated_at;
                for (const key of ['total', 'n_plus_one', 'slow_query', 'duplicate_query', 'unoptimized_query']) {
                    document.getElementById('stat-' + key).textContent = data.stats[key];
                }

                renderRows('rows-n_plus_one', data.top.n_plus_one, 'n+1 issues');
                renderRows('rows-slow_query', data.top.slow_query, 'slow queries');
                renderRows('rows-duplicate_query', data.top.duplicate_query, 'duplicate query issues');
                renderRows('rows-unoptimized_query', data.top.unoptimized_query, 'unoptimized query issues');
            } catch (e) {
                // Silently skip a failed poll — next interval will retry.
            }
        }

        setInterval(refresh, pollMs);
    </script>

</body>
</html>