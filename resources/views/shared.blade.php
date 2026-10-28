<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Сообщение · LiteWMS Chat</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230f766e'%3E%3Cpath d='M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z'/%3E%3C/svg%3E">
    <style>
        :root{--g950:#032b27;--g800:#075650;--g600:#0f766e;--mint:#34d3a1;--bg:#e8f0ee;--ink:#0b1f1d;--muted:#52665f;--line:#d3e0dd;--font:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;flex-direction:column;background:var(--bg);color:var(--ink);font:16px/1.55 var(--font)}
        header{background:linear-gradient(135deg,var(--g950),var(--g800));color:#fff}
        header div{max-width:40rem;margin:0 auto;padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between}
        header a{color:#fff;text-decoration:none;font-weight:800;letter-spacing:.04em}
        header b{color:var(--mint)}
        main{flex:1;width:100%;max-width:40rem;margin:0 auto;padding:1.5rem 1.25rem}
        .card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:1.25rem 1.4rem;box-shadow:0 18px 40px -28px rgb(3 43 39/.5)}
        .meta{display:flex;flex-wrap:wrap;gap:.25rem .75rem;align-items:baseline;color:var(--muted);font-size:.85rem}
        .meta strong{color:var(--ink);font-size:.95rem}
        .fwd{margin:.6rem 0 0;color:var(--g600);font-size:.85rem;font-style:italic}
        .body{margin:.8rem 0 0;white-space:pre-wrap;word-break:break-word;font-size:1.05rem}
        .media{margin-top:.9rem}
        .media img,.media video{max-width:100%;max-height:28rem;border-radius:12px;display:block}
        .media audio{width:100%}
        .file{display:inline-flex;align-items:center;gap:.6rem;padding:.7rem 1rem;border-radius:12px;background:#f1f7f5;color:var(--g600);text-decoration:none;font-weight:600;word-break:break-all}
        .note{margin-top:1rem;font-size:.82rem;color:var(--muted)}
        h1{margin:0 0 .4rem;font-size:1.25rem}
        p{margin:.4rem 0}
        input{width:100%;margin-top:.8rem;padding:.7rem .9rem;border:1px solid var(--line);border-radius:12px;font:inherit}
        input:focus{outline:2px solid rgb(52 211 161/.5)}
        .btn{display:inline-block;margin-top:.9rem;padding:.7rem 1.3rem;border:0;border-radius:99px;background:var(--mint);color:var(--g950);font:600 1rem var(--font);text-decoration:none;cursor:pointer}
        .err{color:#b91c1c;font-size:.9rem}
    </style>
</head>
<body>
<header><div><a href="/">LITE<b>W</b>MS</a><span style="font-size:.85rem;opacity:.8">Сообщение по ссылке</span></div></header>
<main><div class="card" id="root"><p style="color:var(--muted)">Загрузка…</p></div></main>
<script>
(function () {
    var root = document.getElementById('root');
    var token = location.pathname.split('/').filter(Boolean).pop();
    var saved = null;
    try { saved = JSON.parse(localStorage.getItem('lwchat:session') || 'null'); } catch (e) {}

    function el(tag, attrs, text) {
        var n = document.createElement(tag);
        for (var k in (attrs || {})) n.setAttribute(k, attrs[k]);
        if (text != null) n.textContent = text;
        return n;
    }
    function show() { root.textContent = ''; for (var i = 0; i < arguments.length; i++) root.appendChild(arguments[i]); }
    function say(title, text, action) {
        var nodes = [el('h1', null, title), el('p', { style: 'color:var(--muted)' }, text)];
        if (action) nodes.push(action);
        show.apply(null, nodes);
    }
    function fmt(iso) {
        try { return new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }); } catch (e) { return ''; }
    }
    function size(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' МБ' : Math.max(1, Math.round(n / 1024)) + ' КБ'; }

    function render(d) {
        var nodes = [];
        var meta = el('div', { class: 'meta' });
        meta.appendChild(el('strong', null, d.sender.name + (d.sender.role ? ' · ' + d.sender.role : '')));
        meta.appendChild(el('span', null, fmt(d.created_at) + (d.edited ? ' · изменено' : '')));
        nodes.push(meta);
        if (d.forwarded_from) nodes.push(el('p', { class: 'fwd' }, 'Переслано от ' + d.forwarded_from));
        if (d.body) nodes.push(el('p', { class: 'body' }, d.body));
        if (d.file) {
            var box = el('div', { class: 'media' });
            if (d.file.kind === 'image') box.appendChild(el('img', { src: d.file.url, alt: d.file.name }));
            else if (d.file.kind === 'video') box.appendChild(el('video', { src: d.file.url, controls: '', preload: 'metadata' }));
            else box.appendChild(el('a', { class: 'file', href: d.file.url, rel: 'noopener' }, d.file.name + ' · ' + size(d.file.size)));
            nodes.push(box);
        }
        if (d.voice) {
            var v = el('div', { class: 'media' });
            v.appendChild(el('audio', { src: d.voice.url, controls: '', preload: 'none' }));
            nodes.push(v);
        }
        var note = d.access === 'anyone' ? 'Доступно всем, у кого есть ссылка.' : (d.access === 'app' ? 'Доступно всем, кто вошёл в чат.' : 'Доступно участникам диалога.');
        if (d.expires_at) note += ' Ссылка действует до ' + fmt(d.expires_at) + '.';
        nodes.push(el('p', { class: 'note' }, note));
        if (d.conversation_id) nodes.push(el('a', { class: 'btn', href: '/app?c=' + encodeURIComponent(d.conversation_id) }, 'Открыть в чате'));
        show.apply(null, nodes);
    }

    function askPassword(wrong) {
        var form = el('form');
        form.appendChild(el('h1', null, 'Сообщение защищено паролем'));
        var input = el('input', { type: 'password', autocomplete: 'off', placeholder: 'Пароль', 'aria-label': 'Пароль', required: '' });
        form.appendChild(input);
        if (wrong) form.appendChild(el('p', { class: 'err' }, 'Неверный пароль.'));
        form.appendChild(el('button', { class: 'btn', type: 'submit' }, 'Открыть'));
        form.addEventListener('submit', function (e) { e.preventDefault(); load(input.value); });
        show(form);
        input.focus();
    }

    function load(password) {
        var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
        if (saved && saved.token) headers.Authorization = 'Bearer ' + saved.token;
        fetch('/api/v1/shared/' + encodeURIComponent(token), { method: 'POST', headers: headers, body: JSON.stringify(password ? { password: password } : {}), referrerPolicy: 'no-referrer' })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, json: j }; }); })
            .then(function (res) {
                if (res.status === 200) return render(res.json.data);
                var code = res.json.code;
                if (code === 'password_required') return askPassword(false);
                if (code === 'wrong_password') return askPassword(true);
                if (code === 'auth_required') return say('Нужен вход', res.json.message, el('a', { class: 'btn', href: '/app' }, 'Войти в чат'));
                if (code === 'expired') return say('Срок ссылки истёк', res.json.message);
                if (code === 'forbidden') return say('Нет доступа', res.json.message);
                if (res.status === 429) return say('Слишком много попыток', 'Подождите минуту и попробуйте снова.');
                say('Ссылка не работает', res.json.message || 'Сообщение недоступно.');
            })
            .catch(function () { say('Нет связи', 'Не удалось загрузить сообщение. Обновите страницу.'); });
    }
    load();
})();
</script>
</body>
</html>
