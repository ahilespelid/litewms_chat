/*!
 * LiteWMS Chat — подключение к любому сайту одной строкой.
 *
 *   <script src="https://chat.litewms.ru/embed.js"
 *           data-app="lw_pub_…"
 *           data-token-url="/chat/token"></script>
 *
 * Появится плавающая кнопка чата со счётчиком непрочитанных. Либо встроенный блок:
 *
 *   <lw-chat app="lw_pub_…" token-url="/chat/token" context="request:123" context-title="Заявка №123" view="thread"></lw-chat>
 *
 * Скрипт без зависимостей. Сам чат живёт в iframe с поддомена чата — стили и код сайта-хозяина ему не мешают
 * и наоборот. Общение со страницей — только через postMessage с проверкой origin.
 *
 * JS API:  LWChat.init({ app, tokenUrl | tokenProvider, accent, position, label, mode })
 *          LWChat.open([{ context: { type, id, title, url } }])   LWChat.close()   LWChat.toggle()
 *          LWChat.on('unread' | 'open' | 'close' | 'ready', fn)   LWChat.off(event, fn)   LWChat.unread
 *          LWChat.destroy()
 */
(function () {
    'use strict';

    if (window.LWChat && window.LWChat.__loaded) return;

    var script = document.currentScript;
    var chatOrigin = script && script.src ? new URL(script.src, location.href).origin : null;
    var frames = [];
    var listeners = {};
    var state = { unread: 0, open: false, opts: null, widget: null };

    function emit(type, payload) {
        (listeners[type] || []).slice().forEach(function (fn) {
            try { fn(payload); } catch (e) { console.error('[LWChat]', e); }
        });
    }

    /* ——— токен у сайта-хозяина ——— */
    function fetchToken(opts) {
        if (typeof opts.tokenProvider === 'function') {
            return Promise.resolve(opts.tokenProvider());
        }
        if (!opts.tokenUrl) {
            return Promise.reject(new Error('LWChat: не задан tokenUrl или tokenProvider'));
        }
        return fetch(opts.tokenUrl, { credentials: 'include', headers: { Accept: 'application/json' } })
            .then(function (r) {
                if (!r.ok) throw new Error('Сайт ответил ' + r.status);
                return r.json();
            })
            .then(function (j) {
                var t = j && (j.token || (j.data && j.data.token));
                if (!t) throw new Error('В ответе нет token');
                return t;
            });
    }

    /* ——— iframe ——— */
    function buildSrc(opts, extra) {
        var q = new URLSearchParams({ app: opts.app });
        if (opts.view) q.set('view', opts.view);
        if (opts.accent) q.set('accent', opts.accent);
        var ctx = (extra && extra.context) || opts.context;
        if (ctx && ctx.type && ctx.id !== undefined) {
            q.set('context', ctx.type + ':' + ctx.id);
            if (ctx.title) q.set('context_title', ctx.title);
            if (ctx.url) q.set('context_url', ctx.url);
        }
        return chatOrigin + '/embed?' + q.toString();
    }

    function createFrame(opts, host, extra) {
        var iframe = document.createElement('iframe');
        iframe.src = buildSrc(opts, extra);
        iframe.title = opts.label || 'Чат';
        iframe.setAttribute('allow', 'microphone; clipboard-write; autoplay');
        iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads');
        iframe.setAttribute('referrerpolicy', 'strict-origin');
        iframe.style.cssText = 'border:0;width:100%;height:100%;display:block;background:#fff';
        host.appendChild(iframe);
        var frame = { iframe: iframe, opts: opts, extra: extra || {} };
        frames.push(frame);
        return frame;
    }

    function postTo(frame, message) {
        if (frame.iframe.contentWindow) {
            frame.iframe.contentWindow.postMessage(Object.assign({ lwchat: 1 }, message), chatOrigin);
        }
    }

    window.addEventListener('message', function (event) {
        if (event.origin !== chatOrigin) return;
        var msg = event.data;
        if (!msg || msg.lwchat !== 1) return;
        var frame = frames.filter(function (f) { return f.iframe.contentWindow === event.source; })[0];
        if (!frame) return;

        switch (msg.type) {
            case 'ready':
                postTo(frame, {
                    type: 'init',
                    accent: frame.opts.accent,
                    view: frame.opts.view,
                    context: (frame.extra && frame.extra.context) || frame.opts.context,
                });
                postTo(frame, { type: 'visibility', value: frame.visible !== false });
                emit('ready', {});
                break;
            case 'need-token':
                fetchToken(frame.opts).then(
                    function (token) { postTo(frame, { type: 'token', token: token }); },
                    function (err) { postTo(frame, { type: 'token-error', message: String(err && err.message || err) }); }
                );
                break;
            case 'unread':
                setUnread(msg.total || 0);
                break;
        }
    });

    /* ——— счётчик ——— */
    function setUnread(n) {
        state.unread = n;
        if (state.widget) {
            var b = state.widget.badge;
            b.textContent = n > 99 ? '99+' : String(n);
            b.style.display = n ? 'flex' : 'none';
        }
        emit('unread', n);
    }

    /* ——— стили ——— */
    function injectStyles(accent) {
        if (document.getElementById('lwc-style')) return;
        var s = document.createElement('style');
        s.id = 'lwc-style';
        s.textContent =
            '.lwc-btn{position:fixed;bottom:20px;z-index:2147483000;width:56px;height:56px;border:0;border-radius:50%;cursor:pointer;' +
            'background:var(--lwc-accent,#0f766e);color:#fff;box-shadow:0 6px 20px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transition:transform .15s}' +
            '.lwc-btn:hover{transform:scale(1.06)}.lwc-btn:focus-visible{outline:3px solid #fff;outline-offset:2px;box-shadow:0 0 0 5px var(--lwc-accent,#0f766e)}' +
            '.lwc-badge{position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;border-radius:10px;background:#dc2626;color:#fff;' +
            'font:600 11px/20px system-ui,sans-serif;display:none;align-items:center;justify-content:center;box-sizing:border-box}' +
            '.lwc-panel{position:fixed;bottom:88px;z-index:2147483001;width:384px;max-width:calc(100vw - 24px);height:640px;max-height:calc(100vh - 108px);' +
            'background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,.28);overflow:hidden;display:flex;flex-direction:column;' +
            'opacity:0;visibility:hidden;transform:translateY(12px);transition:opacity .18s,transform .18s,visibility .18s}' +
            '.lwc-panel.lwc-open{opacity:1;visibility:visible;transform:none}' +
            '.lwc-head{flex:0 0 auto;display:flex;align-items:center;gap:8px;padding:0 6px 0 14px;height:40px;background:var(--lwc-accent,#0f766e);color:#fff;font:600 14px system-ui,sans-serif}' +
            '.lwc-head span{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
            '.lwc-x{width:32px;height:32px;border:0;border-radius:8px;background:transparent;color:#fff;font-size:22px;line-height:1;cursor:pointer}.lwc-x:hover{background:rgba(255,255,255,.18)}' +
            '.lwc-body{flex:1 1 auto;min-height:0}' +
            '@media (max-width:520px){.lwc-panel{inset:0!important;width:auto;max-width:none;height:auto;max-height:none;border-radius:0}}' +
            'lw-chat{display:block;min-height:420px;height:600px;border-radius:12px;overflow:hidden;border:1px solid rgba(0,0,0,.1)}';
        document.head.appendChild(s);
    }

    /* ——— плавающий виджет ——— */
    var ICON = '<svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>';

    function buildWidget(opts) {
        var side = opts.position === 'left' ? 'left' : 'right';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'lwc-btn';
        btn.style[side] = '20px';
        btn.setAttribute('aria-label', opts.label || 'Открыть чат');
        btn.innerHTML = ICON + '<span class="lwc-badge" aria-live="polite"></span>';

        var panel = document.createElement('div');
        panel.className = 'lwc-panel';
        panel.style[side] = '20px';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', opts.label || 'Чат');
        panel.innerHTML = '<div class="lwc-head"><span></span><button type="button" class="lwc-x" aria-label="Закрыть">&times;</button></div><div class="lwc-body"></div>';
        panel.querySelector('span').textContent = opts.label || 'Чат';

        document.body.appendChild(panel);
        document.body.appendChild(btn);
        document.documentElement.style.setProperty('--lwc-accent', opts.accent || '#0f766e');

        var widget = { btn: btn, panel: panel, badge: btn.querySelector('.lwc-badge'), frame: null };
        var body = panel.querySelector('.lwc-body');

        widget.ensureFrame = function (extra) {
            if (!widget.frame) {
                widget.frame = createFrame(opts, body, extra);
                widget.frame.visible = false;
            }
            return widget.frame;
        };

        btn.addEventListener('click', function () { api.toggle(); });
        panel.querySelector('.lwc-x').addEventListener('click', function () { api.close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && state.open) api.close(); });

        // Фоновая загрузка: счётчик и realtime должны работать, пока окно закрыто.
        var preload = function () { widget.ensureFrame(); };
        (window.requestIdleCallback || function (fn) { return setTimeout(fn, 800); })(preload);

        return widget;
    }

    /* ——— публичный API ——— */
    var api = {
        __loaded: true,

        init: function (opts) {
            if (!chatOrigin) throw new Error('LWChat: подключите скрипт тегом <script src="…/embed.js">');
            if (!opts || !opts.app) throw new Error('LWChat: укажите app');
            if (state.opts) return api;
            state.opts = opts;
            injectStyles(opts.accent);
            if ((opts.mode || 'floating') === 'floating') {
                var make = function () { state.widget = buildWidget(opts); };
                document.body ? make() : document.addEventListener('DOMContentLoaded', make);
            }
            return api;
        },

        open: function (options) {
            var w = state.widget;
            if (!w) return;
            var extra = options && options.context ? { context: options.context } : undefined;
            var frame = w.ensureFrame(extra);
            if (extra && w.frame) {
                // уже загруженный чат переключаем на нужный диалог без перезагрузки
                postTo(frame, { type: 'open', context: options.context });
            }
            state.open = true;
            w.panel.classList.add('lwc-open');
            frame.visible = true;
            postTo(frame, { type: 'visibility', value: true });
            emit('open', {});
        },

        close: function () {
            var w = state.widget;
            if (!w) return;
            state.open = false;
            w.panel.classList.remove('lwc-open');
            if (w.frame) {
                w.frame.visible = false;
                postTo(w.frame, { type: 'visibility', value: false });
            }
            w.btn.focus();
            emit('close', {});
        },

        toggle: function () { state.open ? api.close() : api.open(); },

        on: function (type, fn) { (listeners[type] = listeners[type] || []).push(fn); return api; },
        off: function (type, fn) { listeners[type] = (listeners[type] || []).filter(function (f) { return f !== fn; }); return api; },

        get unread() { return state.unread; },

        destroy: function () {
            frames.forEach(function (f) { f.iframe.remove(); });
            frames = [];
            if (state.widget) { state.widget.btn.remove(); state.widget.panel.remove(); }
            state = { unread: 0, open: false, opts: null, widget: null };
        },

        _createInline: function (opts, host) { return createFrame(opts, host); },
    };

    /* ——— <lw-chat> ——— */
    if (window.customElements && !customElements.get('lw-chat')) {
        customElements.define('lw-chat', class extends HTMLElement {
            connectedCallback() {
                if (this._frame || !chatOrigin) return;
                var ctx = (this.getAttribute('context') || '').match(/^([^:]+):(.+)$/);
                var opts = {
                    app: this.getAttribute('app') || (state.opts && state.opts.app),
                    tokenUrl: this.getAttribute('token-url') || (state.opts && state.opts.tokenUrl),
                    tokenProvider: state.opts && state.opts.tokenProvider,
                    accent: this.getAttribute('accent') || (state.opts && state.opts.accent),
                    view: this.getAttribute('view') || 'full',
                    label: this.getAttribute('label') || 'Чат',
                    context: ctx ? { type: ctx[1], id: ctx[2], title: this.getAttribute('context-title') || undefined, url: this.getAttribute('context-url') || undefined } : undefined,
                };
                if (!opts.app) { console.error('LWChat: у <lw-chat> нет атрибута app'); return; }
                injectStyles(opts.accent);
                this._frame = createFrame(opts, this);
                this._frame.visible = true;
            }
            disconnectedCallback() {
                if (!this._frame) return;
                frames = frames.filter(function (f) { return f !== this._frame; }, this);
                this._frame = null;
                this.textContent = '';
            }
        });
    }

    window.LWChat = api;

    /* ——— автозапуск по data-атрибутам тега script ——— */
    if (script && script.dataset && script.dataset.app) {
        var d = script.dataset;
        var ctx = d.context && d.context.match(/^([^:]+):(.+)$/);
        api.init({
            app: d.app,
            tokenUrl: d.tokenUrl,
            accent: d.accent,
            position: d.position,
            label: d.label,
            view: d.view,
            mode: d.mode,
            context: ctx ? { type: ctx[1], id: ctx[2], title: d.contextTitle, url: d.contextUrl } : undefined,
        });
    }
})();
