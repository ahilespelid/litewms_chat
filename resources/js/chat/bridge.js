/**
 * Связь страницы чата (внутри iframe) с сайтом-хозяином через postMessage.
 * Протокол: сообщения вида { lwchat: 1, type, ... }.
 *
 *   iframe → сайт:  ready | need-token | unread {total} | opened {id} | navigate {url} | close
 *   сайт → iframe:  init {accent, context, view} | token {token} | token-error {message}
 *                   | open {context | id} | visibility {value}
 *
 * Безопасность: принимаем сообщения только от window.parent и только с origin из списка приложения;
 * адрес родителя запоминаем из его первого же подтверждённого сообщения и дальше шлём строго ему.
 */
export function createBridge(cfg) {
    const embedded = cfg.mode === 'embed' && window.parent !== window;
    const handlers = {};
    let parentOrigin = null;
    let waiter = null;

    function post(message) {
        if (!embedded) return;
        window.parent.postMessage({ lwchat: 1, ...message }, parentOrigin || '*');
    }

    window.addEventListener('message', (event) => {
        if (!embedded || event.source !== window.parent) return;
        if (!(cfg.allowedOrigins || []).includes(event.origin)) return;

        const msg = event.data;
        if (!msg || msg.lwchat !== 1 || typeof msg.type !== 'string') return;

        parentOrigin = event.origin;

        if (msg.type === 'token' && waiter) {
            waiter.resolve(msg.token);
            waiter = null;
        } else if (msg.type === 'token-error' && waiter) {
            waiter.reject(new Error(msg.message || 'Сайт не выдал токен'));
            waiter = null;
        }

        handlers[msg.type]?.(msg);
    });

    return {
        embedded,
        on(type, fn) {
            handlers[type] = fn;
        },
        emit(type, payload = {}) {
            post({ type, ...payload });
        },
        /** Просит сайт-хозяин выдать свежий handoff-токен (он сам знает, кто вошёл). */
        requestToken(timeoutMs = 10000) {
            if (!embedded) return Promise.reject(new Error('Не во встроенном режиме'));

            return new Promise((resolve, reject) => {
                const timer = setTimeout(() => {
                    waiter = null;
                    reject(new Error('Сайт не ответил на запрос токена'));
                }, timeoutMs);
                waiter = {
                    resolve: (t) => (clearTimeout(timer), resolve(t)),
                    reject: (e) => (clearTimeout(timer), reject(e)),
                };
                post({ type: 'need-token' });
            });
        },
    };
}
