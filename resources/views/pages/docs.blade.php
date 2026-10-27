@extends('pages.layout')

@section('title', 'LiteWMS Chat — API')
@section('description', 'REST API чата LiteWMS: авторизация, диалоги, сообщения, файлы. Интерактивная документация OpenAPI.')

@section('css')
.nav nav a.on{background:rgb(255 255 255/.1);color:#fff}
.docs-hero{background:linear-gradient(135deg,var(--g950),var(--g800));color:#fff;padding:1.2rem 0 4.5rem;position:relative;overflow:hidden}
.docs-hero::before{content:"";position:absolute;inset:0;background-image:linear-gradient(rgb(255 255 255/.045) 1px,transparent 1px),linear-gradient(90deg,rgb(255 255 255/.045) 1px,transparent 1px);background-size:44px 44px;mask-image:linear-gradient(to bottom,#000 30%,transparent)}
.docs-hero .wrap{position:relative}
.docs-hero .eyebrow{display:inline-block;font-size:.75rem;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--mint);margin:2rem 0 .6rem}
.docs-hero h1{margin:0 0 .6rem;font-size:clamp(1.9rem,4vw,2.8rem);line-height:1.15;letter-spacing:-.01em}
.docs-hero p{margin:0 0 1.4rem;max-width:42rem;color:#bfe0d8;font-size:1.05rem}
.docs-hero .row{display:flex;flex-wrap:wrap;gap:.7rem}
.docs-hero .btn{padding:.6rem 1.1rem;font-size:.92rem}
.docs-card{position:relative;margin:-2.8rem auto 0;background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 24px 60px -30px rgb(3 43 39/.45);padding:1.2rem 1.4rem 2rem;min-height:24rem}
#swagger-ui .loading{padding:3rem 0;text-align:center;color:var(--muted)}

/* Swagger UI в цветах бренда */
.swagger-ui{font-family:var(--font);color:var(--ink)}
.swagger-ui .topbar{display:none}
.swagger-ui .wrapper{padding:0}
.swagger-ui .info{margin:1.2rem 0 1.4rem}
.swagger-ui .info .title{font-family:var(--font);color:var(--g900);font-weight:800;letter-spacing:-.01em}
.swagger-ui .info .title small{background:var(--mint-soft);color:var(--g800)}
.swagger-ui .info .title small pre{color:var(--g800)}
.swagger-ui .info .title small:not(.version-stamp) pre,.swagger-ui .info .title small{font-family:var(--font)}
.swagger-ui .markdown code,.swagger-ui .renderedMarkdown code,.swagger-ui code{color:var(--g700);background:#eaf4f1;border-radius:6px;font-family:var(--mono)}
.swagger-ui .info h1,.swagger-ui .info h2,.swagger-ui .info h3,.swagger-ui .info h4,.swagger-ui .markdown h2,.swagger-ui .markdown h3{font-family:var(--font);color:var(--g900)}
.swagger-ui .info table thead tr th{border-bottom:1px solid var(--line)}
.swagger-ui .info .title small.version-stamp{background:var(--g700)}
.swagger-ui .info .title small.version-stamp pre{color:#fff}
.swagger-ui .info p,.swagger-ui .info li,.swagger-ui .info table,.swagger-ui .markdown p,.swagger-ui .renderedMarkdown p{font-family:var(--font);color:var(--muted)}
.swagger-ui .info a{color:var(--g600)}
.swagger-ui .scheme-container{background:#f4f9f7;box-shadow:none;border:1px solid var(--line);border-radius:12px;padding:1rem 1.2rem;margin:0 0 1.5rem}
.swagger-ui .opblock-tag{font-family:var(--font);color:var(--g900);font-weight:700;border-bottom:1px solid var(--line)}
.swagger-ui .opblock-tag:hover{background:#f4f9f7}
.swagger-ui .opblock{border-radius:12px;box-shadow:none;border-width:1px;margin:0 0 .6rem}
.swagger-ui .opblock .opblock-summary{padding:.35rem .6rem}
.swagger-ui .opblock .opblock-summary-method{border-radius:8px;font-family:var(--mono);font-size:.78rem;min-width:4.6rem;padding:.45rem 0}
.swagger-ui .opblock .opblock-summary-path,.swagger-ui .opblock .opblock-summary-path__deprecated{font-family:var(--mono);font-size:.92rem;font-weight:600;color:var(--ink)}
.swagger-ui .opblock .opblock-summary-description{color:var(--muted);font-family:var(--font)}
.swagger-ui .opblock.opblock-get{background:rgb(15 118 110/.05);border-color:#7cc7bd}
.swagger-ui .opblock.opblock-get .opblock-summary-method,.swagger-ui .opblock.opblock-get .tab-header .tab-item.active h4 span::after{background:var(--g600)}
.swagger-ui .opblock.opblock-post{background:rgb(52 211 161/.08);border-color:#5fd3ae}
.swagger-ui .opblock.opblock-post .opblock-summary-method{background:#12a37a}
.swagger-ui .opblock.opblock-put,.swagger-ui .opblock.opblock-patch{background:rgb(217 119 6/.06);border-color:#e8b370}
.swagger-ui .opblock.opblock-put .opblock-summary-method{background:#d97706}
.swagger-ui .opblock.opblock-patch .opblock-summary-method{background:#b45309}
.swagger-ui .opblock.opblock-delete{background:rgb(220 38 38/.05);border-color:#eba3a3}
.swagger-ui .opblock.opblock-delete .opblock-summary-method{background:#dc2626}
.swagger-ui .opblock-body pre.microlight{border-radius:10px;background:var(--g950)!important}
.swagger-ui .opblock-section-header{background:#f4f9f7;box-shadow:none;border-bottom:1px solid var(--line)}
.swagger-ui .opblock-section-header h4{font-family:var(--font);color:var(--g900)}
.swagger-ui .btn{font-family:var(--font);border-radius:99px;font-weight:600;box-shadow:none;border-color:var(--g600);color:var(--g700)}
.swagger-ui .btn:hover{box-shadow:none;background:var(--mint-soft)}
.swagger-ui .btn.authorize{background:var(--mint);border-color:var(--mint);color:var(--g950)}
.swagger-ui .btn.authorize svg{fill:var(--g950)}
.swagger-ui .btn.authorize:hover{background:#5fe0b8}
.swagger-ui .btn.execute{background:var(--g700);border-color:var(--g700);color:#fff}
.swagger-ui .btn.execute:hover{background:var(--g800)}
.swagger-ui .btn.cancel{border-color:#dc2626;color:#dc2626}
.swagger-ui select,.swagger-ui input[type=text],.swagger-ui input[type=password],.swagger-ui input[type=search],.swagger-ui textarea{font-family:var(--font);border-radius:8px;border:1px solid var(--line)}
.swagger-ui input:focus,.swagger-ui textarea:focus,.swagger-ui select:focus{outline:2px solid rgb(52 211 161/.5);outline-offset:0}
.swagger-ui table thead tr td,.swagger-ui table thead tr th,.swagger-ui .parameter__name,.swagger-ui .response-col_status{font-family:var(--font);color:var(--g900)}
.swagger-ui .parameter__name.required::after{color:#dc2626}
.swagger-ui .parameter__type,.swagger-ui .prop-type{color:var(--g600);font-family:var(--mono)}
.swagger-ui section.models{border-radius:12px;border:1px solid var(--line)}
.swagger-ui section.models h4{font-family:var(--font);color:var(--g900)}
.swagger-ui .model-box,.swagger-ui .model{font-family:var(--mono)}
.swagger-ui .responses-inner h4,.swagger-ui .responses-inner h5{font-family:var(--font)}
.swagger-ui .filter .operation-filter-input{border-radius:99px;padding:.5rem 1rem}
.swagger-ui a.nostyle,.swagger-ui .opblock-tag a{color:inherit}
@media (max-width:640px){
  .docs-card{overflow-x:auto}
  .swagger-ui table{display:block;overflow-x:auto}
  .docs-card{padding:.8rem .7rem 1.4rem;margin-top:-2rem;border-radius:14px}
  .swagger-ui .opblock .opblock-summary-path{font-size:.8rem;word-break:break-all}
}
@endsection

@section('body')
<header class="nav solid">
    <div class="wrap">
        <a class="logo" href="/"><span>LITE<b>W</b>MS</span> <i>Chat</i></a>
        <nav>
            <a href="/#features">Возможности</a>
            <a href="/#connect">Подключение</a>
            <a href="/docs" class="on">API</a>
            <a href="/app" class="cta">Открыть чат</a>
        </nav>
    </div>
</header>

<section class="docs-hero">
    <div class="wrap">
        <span class="eyebrow">REST API · OpenAPI 3</span>
        <h1>Документация API</h1>
        <p>Диалоги, сообщения, файлы и вход через JWT. Всё, что умеет интерфейс чата, доступно по API: сайт подключается через серверный токен, мобильный клиент — через обмен токена на сессию. Запросы можно выполнять прямо на этой странице.</p>
        <div class="row">
            <a class="btn primary" href="/docs/openapi.json" target="_blank" rel="noopener">openapi.json</a>
            <a class="btn ghost" href="/#connect">Как подключить сайт</a>
        </div>
    </div>
</section>

<main class="wrap">
    <div class="docs-card">
        <div id="swagger-ui"><div class="loading">Загрузка документации…</div></div>
    </div>
</main>
@endsection

@push('head')
<link rel="stylesheet" href="/vendor/swagger-ui/swagger-ui.css">
@endpush

@push('scripts')
<script src="/vendor/swagger-ui/swagger-ui-bundle.js"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: '/docs/openapi.json',
        dom_id: '#swagger-ui',
        deepLinking: true,
        persistAuthorization: true,
        tryItOutEnabled: true,
        docExpansion: 'list',
        defaultModelsExpandDepth: 0,
        tagsSorter: 'alpha',
    });
</script>
@endpush
