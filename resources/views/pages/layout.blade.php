<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LiteWMS Chat')</title>
    <meta name="description" content="@yield('description', 'Мессенджер для любого сайта: подключается одной строкой, свой REST API и документация.')">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230f766e'%3E%3Cpath d='M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z'/%3E%3C/svg%3E">
    @stack('head')
    <style>
        :root{
            --g950:#032b27;--g900:#053f3a;--g800:#075650;--g700:#0a6d65;--g600:#0f766e;
            --mint:#34d3a1;--mint-soft:#d9f5ea;
            --bg:#e8f0ee;--card:#fff;--ink:#0b1f1d;--muted:#52665f;--line:#d3e0dd;
            --radius:18px;
            --font:'Inter',ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
            --mono:ui-monospace,SFMono-Regular,'JetBrains Mono',Menlo,Consolas,monospace;
        }
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{margin:0;font:16px/1.6 var(--font);color:var(--ink);background:var(--bg);-webkit-font-smoothing:antialiased}
        a{color:var(--g600);text-decoration-color:rgb(15 118 110/.35);text-underline-offset:3px}
        a:hover{color:var(--g800)}
        .wrap{max-width:72rem;margin:0 auto;padding:0 1.5rem}

        /* Шапка */
        .nav{position:absolute;inset:0 0 auto 0;z-index:5}
        .nav.solid{position:static;background:linear-gradient(135deg,var(--g950),var(--g800))}
        .nav .wrap{display:flex;align-items:center;justify-content:space-between;height:4.5rem}
        .logo{display:inline-flex;align-items:center;gap:.65rem;color:#fff;text-decoration:none;font-weight:800;font-size:1.35rem;letter-spacing:.04em}
        .logo b{color:var(--mint);font-weight:800}
        .logo i{font-style:normal;font-size:.7rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;padding:.2rem .55rem;border-radius:99px;background:rgb(255 255 255/.12);color:#d6f5ea}
        .nav nav{display:flex;align-items:center;gap:.4rem}
        .nav nav a{color:#cfe9e3;text-decoration:none;font-size:.95rem;font-weight:500;padding:.5rem .85rem;border-radius:99px;transition:background .15s,color .15s}
        .nav nav a:hover,.nav nav a.on{background:rgb(255 255 255/.1);color:#fff}
        .nav nav a.cta{background:var(--mint);color:var(--g950);font-weight:600}
        .nav nav a.cta:hover{background:#5fe0b8;color:var(--g950)}

        /* Кнопки */
        .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.8rem 1.35rem;border-radius:99px;font-weight:600;font-size:1rem;text-decoration:none;transition:transform .15s,background .15s,box-shadow .15s}
        .btn.primary{background:var(--mint);color:var(--g950);box-shadow:0 8px 24px -8px rgb(52 211 161/.6)}
        .btn.primary:hover{background:#5fe0b8;color:var(--g950);transform:translateY(-1px)}
        .btn.ghost{color:#fff;border:1px solid rgb(255 255 255/.28)}
        .btn.ghost:hover{background:rgb(255 255 255/.1);color:#fff}
        .btn svg{width:1.1em;height:1.1em}

        /* Подвал */
        footer{background:var(--g950);color:#9fbdb6;margin-top:5rem}
        footer .wrap{display:flex;flex-wrap:wrap;gap:1rem 2rem;align-items:center;justify-content:space-between;padding-top:2rem;padding-bottom:2rem;font-size:.9rem}
        footer a{color:#cfe9e3;text-decoration:none}
        footer a:hover{color:#fff}
        footer .logo{font-size:1.05rem}

        @media (max-width:640px){
            .nav nav a:not(.cta){display:none}
            .wrap{padding:0 1.1rem}
        }
        @yield('css')
    </style>
</head>
<body>
@yield('body')

<footer>
    <div class="wrap">
        <a class="logo" href="/"><span>LITE<b>W</b>MS</span></a>
        <span>Чат для сайтов и мобильных клиентов</span>
        <span><a href="/docs">API</a> · <a href="/docs/openapi.json">openapi.json</a> · <a href="/app">Открыть чат</a></span>
    </div>
</footer>
@stack('scripts')
</body>
</html>
