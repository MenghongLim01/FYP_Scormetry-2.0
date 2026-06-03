<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name', 'Scormetry') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <style>
        :root {
            --brand: #24327a;
            --brand-light: #3157f4;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased; }
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        @media (prefers-color-scheme: dark) {
            body { background: linear-gradient(135deg, #0a0a14 0%, #14182b 100%); color: #f1f5f9; }
        }
        .card {
            position: relative;
            overflow: hidden;
            max-width: 520px;
            width: 100%;
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgb(36 50 122 / 0.18);
            padding: 2.5rem 2rem 2rem;
            text-align: center;
        }
        @media (prefers-color-scheme: dark) {
            .card { background: #1a1f37; box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.5); }
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--brand), var(--brand-light));
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, rgba(36, 50, 122, 0.08), rgba(49, 87, 244, 0.08));
            border: 1px solid rgba(36, 50, 122, 0.12);
            border-radius: 999px;
            color: var(--brand);
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
        }
        @media (prefers-color-scheme: dark) {
            .badge { color: #a5b4fc; background: rgba(99, 102, 241, 0.1); border-color: rgba(99, 102, 241, 0.2); }
        }
        .code {
            font-size: 4.5rem;
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.04em;
            background: linear-gradient(135deg, var(--brand), var(--brand-light));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.75rem;
        }
        h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem; }
        p { color: #475569; line-height: 1.6; font-size: 0.95rem; margin-bottom: 1.75rem; }
        @media (prefers-color-scheme: dark) {
            p { color: #94a3b8; }
        }
        .actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .btn-primary {
            background: var(--brand);
            color: white;
        }
        .btn-primary:hover { background: #1b255c; transform: translateY(-1px); }
        .btn-secondary {
            background: transparent;
            color: var(--brand);
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover { background: #f1f5f9; }
        @media (prefers-color-scheme: dark) {
            .btn-secondary { color: #a5b4fc; border-color: #334155; }
            .btn-secondary:hover { background: #1e293b; }
        }
        .footer { margin-top: 1.75rem; font-size: 0.75rem; color: #94a3b8; }
        .footer a { color: var(--brand); text-decoration: none; }
        @media (prefers-color-scheme: dark) {
            .footer a { color: #a5b4fc; }
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">@yield('badge', config('app.name', 'Scormetry'))</span>
        <div class="code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a href="/dashboard" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Go to dashboard
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                Go back
            </a>
        </div>
        <p class="footer">
            Need help? <a href="mailto:support@example.com">Contact support</a>
        </p>
    </div>
</body>
</html>
