import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/** Подключение к Reverb. Каналы авторизуются тем же Bearer-токеном, что и REST API. */
export function createRealtime({ cfg, getToken }) {
    const rt = cfg.realtime || {};
    let echo = null;

    function connect() {
        if (echo) return echo;
        if (!rt.key) return null;

        echo = new Echo({
            broadcaster: 'reverb',
            key: rt.key,
            wsHost: rt.host,
            wsPort: rt.port,
            wssPort: rt.port,
            forceTLS: rt.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authorizer: (channel) => ({
                authorize: (socketId, callback) => {
                    fetch('/api/v1/broadcasting/auth', {
                        method: 'POST',
                        headers: { Authorization: `Bearer ${getToken()}`, 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
                    })
                        .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`auth ${r.status}`))))
                        .then((data) => callback(null, data))
                        .catch((e) => callback(e, null));
                },
            }),
        });

        return echo;
    }

    return {
        connect,
        get socketId() {
            return echo?.socketId?.() || null;
        },
        /** Личный канал: все события участника. */
        listenPersonal(participantId, handlers) {
            const channel = connect()?.private(`participant.${participantId}`);
            if (!channel) return;
            for (const [name, fn] of Object.entries(handlers)) channel.listen(`.${name}`, fn);
        },
        /** Presence открытого диалога: «кто здесь» и «печатает…». */
        joinConversation(id, { here, joining, leaving, typing }) {
            const channel = connect()?.join(`conversation.${id}`);
            if (!channel) return null;
            channel.here(here).joining(joining).leaving(leaving).listen('.typing', typing);

            return () => echo?.leave(`conversation.${id}`);
        },
        onState(fn) {
            connect()?.connector?.pusher?.connection?.bind('state_change', fn);
        },
        disconnect() {
            echo?.disconnect();
            echo = null;
        },
    };
}
