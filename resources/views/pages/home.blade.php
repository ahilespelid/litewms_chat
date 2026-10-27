@extends('pages.layout')

@section('title', 'LiteWMS Chat')

@section('css')
        /* Первый экран */
        .hero{position:relative;overflow:hidden;color:#fff;background:linear-gradient(135deg,var(--g950) 0%,var(--g800) 62%,var(--g700) 100%);padding:9.5rem 0 7rem}
        .hero::before{content:"";position:absolute;inset:0;background-image:linear-gradient(rgb(255 255 255/.045) 1px,transparent 1px),linear-gradient(90deg,rgb(255 255 255/.045) 1px,transparent 1px);background-size:46px 46px;-webkit-mask-image:radial-gradient(70% 90% at 70% 40%,#000 20%,transparent 75%);mask-image:radial-gradient(70% 90% at 70% 40%,#000 20%,transparent 75%)}
        .hero::after{content:"";position:absolute;width:42rem;height:42rem;right:-10rem;top:-14rem;border-radius:50%;background:radial-gradient(closest-side,rgb(52 211 161/.28),transparent)}
        .hero .wrap{position:relative;z-index:1;display:grid;grid-template-columns:1.05fr .95fr;gap:3.5rem;align-items:center}
        .eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.82rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--mint)}
        .eyebrow::before{content:"";width:.5rem;height:.5rem;border-radius:50%;background:var(--mint);box-shadow:0 0 0 4px rgb(52 211 161/.25)}
        .hero h1{margin:.9rem 0 1.1rem;font-size:clamp(2.2rem,5vw,3.6rem);line-height:1.08;font-weight:800;letter-spacing:-.02em}
        .hero h1 em{font-style:normal;color:var(--mint)}
        .hero p.lead{margin:0 0 2rem;max-width:34rem;font-size:1.15rem;color:#c3ddd7}
        .hero p.lead a{color:#fff;text-decoration-color:var(--mint)}
        .hero .actions{display:flex;flex-wrap:wrap;gap:.8rem}

        /* Макет окна чата */
        .mock{position:relative;background:#fff;border-radius:22px;box-shadow:0 40px 80px -24px rgb(0 0 0/.55),0 0 0 1px rgb(255 255 255/.08);overflow:hidden;color:var(--ink);transform:rotate(1.2deg)}
        .mock-head{display:flex;align-items:center;gap:.75rem;padding:.9rem 1.1rem;border-bottom:1px solid var(--line)}
        .mock-ava{width:2.4rem;height:2.4rem;border-radius:50%;display:grid;place-items:center;background:var(--g600);color:#fff;font-weight:700;font-size:.95rem}
        .mock-head strong{display:block;font-size:.95rem;line-height:1.2}
        .mock-head small{color:var(--muted);font-size:.78rem}
        .mock-head small::before{content:"";display:inline-block;width:.45rem;height:.45rem;margin-right:.35rem;border-radius:50%;background:#22c55e}
        .mock-body{padding:1.1rem;display:flex;flex-direction:column;gap:.55rem;background:#f1f6f5;background-image:radial-gradient(circle at 1px 1px,rgb(15 118 110/.08) 1px,transparent 0);background-size:20px 20px}
        .msg{max-width:82%;padding:.55rem .85rem;border-radius:16px;font-size:.92rem;line-height:1.4;box-shadow:0 1px 1px rgb(0 0 0/.05)}
        .msg.in{align-self:flex-start;background:#fff;border-bottom-left-radius:5px}
        .msg.out{align-self:flex-end;background:var(--g600);color:#fff;border-bottom-right-radius:5px}
        .msg time{display:block;margin-top:.15rem;font-size:.68rem;opacity:.65;text-align:right}
        .sys{align-self:center;margin:.2rem 0;padding:.25rem .8rem;border-radius:99px;background:var(--mint-soft);color:var(--g800);font-size:.76rem;font-weight:600}
        .mock-input{display:flex;align-items:center;gap:.6rem;padding:.8rem 1.1rem;border-top:1px solid var(--line)}
        .mock-input span{flex:1;padding:.55rem .9rem;border-radius:99px;background:#eef3f2;color:#8aa09a;font-size:.88rem}
        .mock-input i{width:2.2rem;height:2.2rem;border-radius:50%;background:var(--g600);display:grid;place-items:center}
        .mock-input i svg{width:1rem;height:1rem;color:#fff}

        /* Секции */
        section{padding:5rem 0 0}
        .head{max-width:40rem;margin:0 0 2.5rem}
        .head h2{margin:0 0 .6rem;font-size:clamp(1.6rem,3vw,2.2rem);line-height:1.15;letter-spacing:-.015em}
        .head p{margin:0;color:var(--muted);font-size:1.05rem}
        .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.1rem}
        .card{background:var(--card);border-radius:var(--radius);padding:1.5rem;border:1px solid var(--line);transition:transform .2s,box-shadow .2s,border-color .2s}
        .card:hover{transform:translateY(-3px);box-shadow:0 18px 40px -22px rgb(5 63 58/.45);border-color:#b9d3cd}
        .ico{width:2.8rem;height:2.8rem;border-radius:14px;display:grid;place-items:center;background:var(--mint-soft);color:var(--g700);margin-bottom:1rem}
        .ico svg{width:1.4rem;height:1.4rem}
        .card h3{margin:0 0 .4rem;font-size:1.08rem}
        .card p{margin:0;color:var(--muted);font-size:.96rem}

        /* Подключение */
        .steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.1rem;counter-reset:s}
        .step{position:relative;background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:1.5rem;display:flex;flex-direction:column}
        .step::before{counter-increment:s;content:counter(s);display:grid;place-items:center;width:2rem;height:2rem;border-radius:50%;background:var(--g800);color:#fff;font-weight:700;font-size:.9rem;margin-bottom:.9rem}
        .step h3{margin:0 0 .35rem;font-size:1.05rem}
        .step p{margin:0 0 1rem;color:var(--muted);font-size:.94rem}
        .code{position:relative;margin-top:auto;background:var(--g950);color:#d6efe8;border-radius:12px;padding:2.3rem 1rem 1rem 1.1rem;overflow:auto;font:.82rem/1.55 var(--mono);white-space:pre}
        .code .k{color:var(--mint)} .code .s{color:#ffd58a} .code .c{color:#6f9a91}
        .copy{position:absolute;top:.45rem;right:.5rem;border:0;border-radius:8px;padding:.3rem .6rem;background:rgb(255 255 255/.1);color:#cfe9e3;font:600 .72rem var(--font);cursor:pointer}
        .copy:hover{background:rgb(255 255 255/.2)}

        /* Блок документации */
        .docs{display:grid;grid-template-columns:1.2fr .8fr;gap:2rem;align-items:center;background:linear-gradient(135deg,var(--g900),var(--g700));color:#fff;border-radius:26px;padding:2.6rem;position:relative;overflow:hidden}
        .docs::after{content:"";position:absolute;right:-6rem;bottom:-9rem;width:22rem;height:22rem;border-radius:50%;background:radial-gradient(closest-side,rgb(52 211 161/.3),transparent)}
        .docs h2{margin:0 0 .6rem;font-size:1.9rem;letter-spacing:-.01em}
        .docs p{margin:0 0 1.5rem;color:#c3ddd7;max-width:30rem}
        .docs .actions{display:flex;flex-wrap:wrap;gap:.8rem;position:relative;z-index:1}
        .routes{position:relative;z-index:1;background:rgb(3 43 39/.55);border:1px solid rgb(255 255 255/.12);border-radius:16px;padding:1rem 1.2rem;font:.82rem/1.9 var(--mono);color:#d6efe8}
        .routes span{display:inline-block;min-width:3.6rem;font-weight:700;color:var(--mint)}
        .routes .p{color:#ffd58a}

        @media (max-width:960px){
            .hero{padding:8rem 0 4.5rem}
            .hero .wrap{grid-template-columns:minmax(0,1fr)}
            .mock{transform:none;max-width:30rem}
            .grid,.steps{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
            .docs{grid-template-columns:minmax(0,1fr)}
        }
        @media (max-width:640px){
            .grid,.steps{grid-template-columns:minmax(0,1fr)}
            .docs{padding:1.8rem}
        }
@endsection

@section('body')
<header class="nav">
    <div class="wrap">
        <a class="logo" href="/"><span>LITE<b>W</b>MS</span> <i>Chat</i></a>
        <nav>
            <a href="#features">Возможности</a>
            <a href="#connect">Подключение</a>
            <a href="/docs">API</a>
            <a class="cta" href="/app">Открыть чат</a>
        </nav>
    </div>
</header>

<main>
    <div class="hero">
        <div class="wrap">
            <div>
                <span class="eyebrow">Чат для сайтов</span>
                <h1>Мессенджер, который <em>встраивается</em> в любой сайт</h1>
                <p class="lead">Подключается к любому сайту одной строкой и имеет своё <a href="/docs">API</a>.</p>
                <div class="actions">
                    <a class="btn primary" href="/app">Открыть чат
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a class="btn ghost" href="/docs">Документация API</a>
                </div>
            </div>

            <div class="mock" aria-hidden="true">
                <div class="mock-head">
                    <div class="mock-ava">С</div>
                    <div><strong>Склад · Заявка №1042</strong><small>на связи</small></div>
                </div>
                <div class="mock-body">
                    <div class="msg in">Здравствуйте! Приёмка завтра в 10:00, всё в силе?<time>09:41</time></div>
                    <div class="msg out">Да, привезём 24 коробки. Этикетки приложил.<time>09:42</time></div>
                    <div class="sys">Заявка принята складом</div>
                    <div class="msg in">Принято, ждём. Ячейки уже подготовлены.<time>09:44</time></div>
                </div>
                <div class="mock-input"><span>Написать сообщение…</span><i><svg viewBox="0 0 24 24"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.3 10l12.6 2-12.6 2z"/></svg></i></div>
            </div>
        </div>
    </div>

    <div class="wrap">
        <section id="features">
            <div class="head">
                <h2>Всё, что нужно для переписки внутри продукта</h2>
                <p>Один чат для кабинета, мобильного клиента и внешних сайтов: пользователи и роли приходят от вашего сайта.</p>
            </div>
            <div class="grid">
                <div class="card"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg></div><h3>Диалоги по сущностям</h3><p>Переписку можно привязать к заявке, заказу или любой записи сайта: у каждой своя история со складом.</p></div>
                <div class="card"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg></div><h3>Реальное время</h3><p>Сообщения, набор текста, прочтение и присутствие приходят мгновенно по WebSocket.</p></div>
                <div class="card"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m21 11.5-8.6 8.6a5.5 5.5 0 0 1-7.8-7.8l8.6-8.6a3.7 3.7 0 0 1 5.2 5.2l-8.6 8.6a1.8 1.8 0 0 1-2.6-2.6l7.9-7.9"/></svg></div><h3>Файлы и голосовые</h3><p>Вложения и голосовые сообщения до 25 МБ с предпросмотром прямо в ленте.</p></div>
                <div class="card"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.4 8.2 8 9 4.6-.8 8-4 8-9V6z"/><path d="m9 12 2.2 2.2L15.5 10"/></svg></div><h3>Единый вход</h3><p>Человека подтверждает подписанный токен вашего сайта. Отдельной регистрации в чате нет.</p></div>
                <div class="card"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7zM10 20a2 2 0 0 0 4 0"/></svg></div><h3>Уведомления</h3><p>Счётчик непрочитанного в меню сайта и web push, когда вкладка чата закрыта.</p></div>
                <div class="card"><div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 8-5 4 5 4M16 8l5 4-5 4M14 5l-4 14"/></svg></div><h3>Своё API</h3><p>REST API для клиентов и серверный API для интеграций, спецификация OpenAPI и Swagger.</p></div>
            </div>
        </section>

        <section id="connect">
            <div class="head">
                <h2>Подключение за три шага</h2>
                <p>Создайте приложение, выдайте токен входа со своего бэкенда и добавьте скрипт на страницу.</p>
            </div>
            <div class="steps">
                <div class="step">
                    <h3>Создайте приложение</h3>
                    <p>Команда печатает публичный ключ и секреты один раз. Сайты хранятся в базе, добавлять их можно позже.</p>
                    <div class="code"><button class="copy" type="button" data-copy>Копировать</button>php artisan chat:app <span class="s">"Мой сайт"</span> \
  --origin=<span class="s">https://example.com</span></div>
                </div>
                <div class="step">
                    <h3>Выдайте токен входа</h3>
                    <p>Эндпоинт вашего сайта подписывает JWT секретом приложения и отдаёт его вошедшему пользователю.</p>
                    <div class="code"><span class="c">// iss, sub, exp, jti, name</span>
<span class="k">JWT</span>::encode(<span class="s">$claims</span>, <span class="s">$secret</span>, <span class="s">'HS256'</span>);</div>
                </div>
                <div class="step">
                    <h3>Добавьте скрипт</h3>
                    <p>Появится кнопка чата со счётчиком непрочитанного. Окно открывается поверх страницы.</p>
                    <div class="code"><button class="copy" type="button" data-copy>Копировать</button>&lt;script src="{{ url('/embed.js') }}"
        data-app="<span class="s">lw_pub_…</span>"
        data-token-url="<span class="s">/chat/token</span>"&gt;&lt;/script&gt;</div>
                </div>
            </div>
        </section>

        <section id="api">
            <div class="docs">
                <div style="position:relative;z-index:1">
                    <h2>Документация API</h2>
                    <p>Полное описание методов, схем и примеров. Запросы можно выполнять прямо из браузера.</p>
                    <div class="actions">
                        <a class="btn primary" href="/docs">Открыть Swagger</a>
                        <a class="btn ghost" href="/docs/openapi.json">openapi.json</a>
                    </div>
                </div>
                <div class="routes" aria-hidden="true">
                    <div><span>POST</span><span class="p">/api/v1/auth/exchange</span></div>
                    <div><span>GET</span><span class="p">/api/v1/conversations</span></div>
                    <div><span>POST</span><span class="p">/api/v1/conversations/{id}/messages</span></div>
                    <div><span>POST</span><span class="p">/api/v1/server/conversations</span></div>
                </div>
            </div>
        </section>
    </div>
</main>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-copy]').forEach(function (b) {
        b.addEventListener('click', function () {
            var box = b.closest('.code'), clone = box.cloneNode(true);
            clone.querySelector('[data-copy]').remove();
            navigator.clipboard && navigator.clipboard.writeText(clone.textContent).then(function () {
                b.textContent = 'Скопировано';
                setTimeout(function () { b.textContent = 'Копировать'; }, 1500);
            });
        });
    });
</script>
@endpush
