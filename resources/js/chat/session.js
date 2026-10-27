import { ApiError } from './api.js';

const KEY = 'lwchat:session';

const store = {
    get() {
        try {
            return JSON.parse(localStorage.getItem(KEY) || 'null');
        } catch {
            return null;
        }
    },
    set(value) {
        try {
            value ? localStorage.setItem(KEY, JSON.stringify(value)) : localStorage.removeItem(KEY);
        } catch {
            /* приватный режим / заблокированное хранилище — работаем без запоминания */
        }
    },
};

/**
 * Вход в чат. Источники учётных данных по порядку:
 *   1. личная ссылка /l/{secret} (гости и операторы без сайта-хозяина);
 *   2. #token= (возврат с кнопки «Войти через сайт») или ?token= (кнопка «Открыть чат» на сайте-хозяине) —
 *      handoff-токен в адресе; из адреса вырезается сразу;
 *   3. сохранённый рабочий токен (только полная страница /app);
 *   4. встроенный режим: запрос handoff-токена у страницы-хозяина через postMessage;
 *   5. гостевая форма, если приложение её разрешает.
 */
export function createSession({ cfg, bridge, rawFetch }) {
    const state = { token: null, expiresAt: null, participant: null, app: null };

    const persist = () => {
        // В iframe хранилище ненадёжно (разделяется по сайтам), и там токен всё равно берётся у хозяина.
        if (!bridge.embedded) {
            store.set(state.token ? { token: state.token, expiresAt: state.expiresAt } : null);
        }
    };

    function apply(data) {
        state.token = data.token;
        state.expiresAt = data.expires_at;
        state.participant = data.participant;
        state.app = data.app;
        persist();

        return data;
    }

    async function post(path, body) {
        const res = await rawFetch(path, body);
        const json = await res.json().catch(() => null);
        if (!res.ok) throw new ApiError(res.status, json);

        return json.data;
    }

    const exchange = async (jwt) => apply(await post('/auth/exchange', { token: jwt }));

    return {
        state,
        get token() {
            return state.token;
        },

        /** @returns {'ready'|'guest-form'|'need-login'} */
        async start() {
            if (cfg.linkSecret) {
                apply(await post('/auth/link', { secret: cfg.linkSecret }));
                history.replaceState(null, '', '/app');

                return 'ready';
            }

            const url = new URL(location.href);
            // Фрагмент предпочтительнее запроса: он не попадает в логи серверов и в Referer.
            const jwt = new URLSearchParams(location.hash.replace(/^#/, '')).get('token') || url.searchParams.get('token');
            if (jwt && !bridge.embedded) {
                url.searchParams.delete('token');
                // pathname + search без hash: фрагмент с токеном убирается из адреса вместе с запросом.
                history.replaceState(null, '', url.pathname + url.search);
                try {
                    await exchange(jwt);

                    return 'ready';
                } catch {
                    /* просрочен — пробуем остальные способы */
                }
            }

            if (!bridge.embedded) {
                const saved = store.get();
                if (saved?.token && (!saved.expiresAt || new Date(saved.expiresAt) > new Date())) {
                    state.token = saved.token;
                    state.expiresAt = saved.expiresAt;
                    try {
                        const res = await fetch('/api/v1/me', { headers: { Authorization: `Bearer ${saved.token}`, Accept: 'application/json' } });
                        if (res.ok) {
                            const me = (await res.json()).data;
                            state.participant = me.participant;
                            state.app = me.app;

                            return 'ready';
                        }
                    } catch {
                        /* сеть — ниже покажем экран входа */
                    }
                    state.token = null;
                    persist();
                }
            }

            if (bridge.embedded) {
                await exchange(await bridge.requestToken());

                return 'ready';
            }

            return cfg.appKey ? 'guest-form' : 'need-login';
        },

        async guest(name, email) {
            return apply(await post('/auth/guest', { app_key: cfg.appKey, name, email: email || undefined }));
        },

        /** Обновить токен: во встроенном режиме у хозяина, иначе — нечем (нужно зайти заново). */
        async refresh() {
            if (!bridge.embedded) return false;
            try {
                await exchange(await bridge.requestToken());

                return true;
            } catch {
                return false;
            }
        },

        /** Пора ли обновлять токен заранее (осталось меньше суток). */
        get expiresSoon() {
            return !!state.expiresAt && new Date(state.expiresAt).getTime() - Date.now() < 86400000;
        },

        async logout(api) {
            try {
                await api.post('/auth/logout');
            } catch {
                /* токен и так мог умереть */
            }
            state.token = null;
            persist();
        },
    };
}
