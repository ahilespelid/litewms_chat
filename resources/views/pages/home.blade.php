<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LiteWMS Chat</title>
    <style>
        body{margin:0;font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0f172a;background:#f4f7f6}
        main{max-width:44rem;margin:0 auto;padding:3rem 1.25rem}
        h1{margin:0 0 .25rem;font-size:1.6rem}
        p.lead{margin:0 0 2rem;color:#475569}
        a{color:#0f766e}
        pre{background:#0f172a;color:#e2e8f0;padding:1rem;border-radius:.75rem;overflow:auto;font-size:.85rem}
        code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
        .card{background:#fff;border-radius:1rem;padding:1.25rem 1.5rem;margin-bottom:1rem;box-shadow:0 1px 2px rgb(0 0 0/.06)}
    </style>
</head>
<body>
<main>
    <h1>LiteWMS Chat</h1>
    <p class="lead">Мессенджер, который подключается к любому сайту одной строкой и имеет своё API для мобильного приложения.</p>

    <div class="card">
        <strong>Подключить к сайту</strong>
        <pre><code>&lt;script src="{{ url('/embed.js') }}"
        data-app="lw_pub_…"
        data-token-url="/chat/token"&gt;&lt;/script&gt;</code></pre>
        <p>Скрипт сам добавит кнопку чата со счётчиком непрочитанных. Подробности — в README репозитория.</p>
    </div>

    <div class="card">
        <strong>Документация API</strong>
        <p style="margin:.25rem 0 0"><a href="/docs">Swagger UI</a> · <a href="/docs/openapi.json">openapi.json</a></p>
    </div>
</main>
</body>
</html>
