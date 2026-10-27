import Alpine from 'alpinejs';
import { createApi, ApiError } from './api.js';
import { createBridge } from './bridge.js';
import { createSession } from './session.js';
import { createRealtime } from './realtime.js';
import { createRecorder } from './voice-recorder.js';
import { avatarColor, dayLabel, formatDuration, formatFileSize, formatTime, initials, linkify, listTime } from './format.js';

const cfg = window.CHAT || {};
const PAGE = 40;

function chatApp() {
    const bridge = createBridge(cfg);
    const session = createSession({
        cfg,
        bridge,
        rawFetch: (path, body) =>
            fetch('/api/v1' + path, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) }),
    });
    const realtime = createRealtime({ cfg, getToken: () => session.token });
    const api = createApi({
        getToken: () => session.token,
        getSocketId: () => realtime.socketId,
        onUnauthorized: () => session.refresh(),
    });

    let recorder = null;
    let recTimer = null;
    let typingSentAt = 0;
    let leavePresence = null;
    let readTimer = null;
    let unreadTimer = null;
    const typingTimers = {};

    return {
        // ——— состояние
        embedded: bridge.embedded,
        phase: 'loading', // loading | ready | guest-form | need-login | error
        error: '',
        me: null,
        app: null,
        conversations: [],
        activeId: null,
        messages: [],
        hasOlder: false,
        loadingOlder: false,
        loadingThread: false,
        draft: '',
        replyTo: null,
        editing: null,
        search: '',
        onlyUnread: false,
        mobileThread: false,
        recording: false,
        recSeconds: 0,
        sending: 0,
        typing: {}, // participantId -> name
        online: {}, // participantId -> true (в открытом диалоге)
        unreadTotal: 0,
        guest: { name: '', email: '' },
        peopleOpen: false,
        people: [],
        peopleQuery: '',
        pushState: 'unknown', // unknown | on | off | unsupported
        hostVisible: true,
        connected: true,
        toast: '',
        layout: cfg.view === 'thread' ? 'thread' : 'full',
        deleteTarget: null,

        // ——— вычисляемое
        get active() {
            return this.conversations.find((c) => c.id === this.activeId) || null;
        },
        get filtered() {
            const q = this.search.trim().toLowerCase();

            return this.conversations.filter((c) => (!this.onlyUnread || c.unread_count > 0) && (!q || c.title.toLowerCase().includes(q)));
        },
        get items() {
            const out = [];
            let prev = null;
            let day = null;
            for (const m of this.messages) {
                const d = new Date(m.created_at).toDateString();
                if (d !== day) {
                    out.push({ kind: 'day', key: 'd' + d, label: dayLabel(m.created_at) });
                    day = d;
                    prev = null;
                }
                const group = prev && prev.sender?.id === m.sender?.id && m.type !== 'system' && prev.type !== 'system'
                    && new Date(m.created_at) - new Date(prev.created_at) < 300000;
                out.push({ kind: 'msg', key: m.id ?? m.client_uuid, m, first: !group });
                prev = m;
            }

            return out;
        },
        get typingNames() {
            return Object.values(this.typing);
        },
        get headerSub() {
            const c = this.active;
            if (!c) return '';
            if (this.typingNames.length) return '';
            if (c.type === 'group') return `${c.member_count} участн.`;
            const others = (c.members || []).filter((m) => m.id !== this.me?.id && !m.is_operator);
            if (c.type === 'support' && this.me?.is_operator) return others[0]?.role || (others[0]?.kind === 'guest' ? 'Гость' : '');
            if (c.type === 'support') return 'Обычно отвечаем в рабочее время';

            return others[0]?.role || '';
        },
        get othersOnline() {
            return Object.keys(this.online).filter((id) => Number(id) !== this.me?.id).length;
        },

        // ——— запуск
        async init() {
            document.documentElement.style.setProperty('--accent', cfg.accent || '#0f766e');

            bridge.on('init', (m) => {
                if (m.accent) document.documentElement.style.setProperty('--accent', m.accent);
                if (m.view) this.layout = m.view === 'thread' ? 'thread' : 'full';
                if (m.context) cfg.context = m.context;
            });
            bridge.on('open', (m) => this.openFromHost(m));
            bridge.on('visibility', (m) => {
                this.hostVisible = !!m.value;
                if (m.value) this.scheduleRead();
            });
            bridge.emit('ready');

            document.addEventListener('visibilitychange', () => this.scheduleRead());
            window.addEventListener('focus', () => this.scheduleRead());

            try {
                this.phase = await session.start();
            } catch (e) {
                this.fail(e);

                return;
            }
            if (this.phase === 'ready') await this.boot();
        },

        fail(e) {
            this.phase = e instanceof ApiError && e.status === 401 ? 'need-login' : 'error';
            this.error = e?.text || e?.message || 'Не удалось подключиться к чату';
        },

        async boot() {
            this.me = session.state.participant;
            this.app = session.state.app;
            if (this.app?.accent && !cfg.accent) document.documentElement.style.setProperty('--accent', this.app.accent);
            if (!bridge.embedded) document.title = this.app?.title || 'Чат';

            try {
                await this.loadConversations();
            } catch (e) {
                this.fail(e);

                return;
            }
            this.phase = 'ready';

            realtime.listenPersonal(this.me.id, {
                'message.created': (e) => this.onMessage(e, true),
                'message.updated': (e) => this.onMessage(e, false),
                'message.read': (e) => this.onRead(e),
                'conversation.updated': (e) => this.upsertConversation(e.conversation),
                'conversation.removed': (e) => this.removeConversation(e.conversation_id),
            });
            realtime.onState((s) => this.onConnection(s));

            this.refreshUnread();
            this.checkPush();

            const wanted = new URLSearchParams(location.search).get('c');
            if (wanted) await this.openConversation(wanted);
            else if (cfg.context) await this.openContext(cfg.context);
            else if (this.layout === 'full' && window.matchMedia('(min-width: 768px)').matches && this.conversations[0]) await this.openConversation(this.conversations[0].id);

            // Токен подходит к концу — заранее берём новый у сайта (во встроенном режиме).
            setInterval(() => session.expiresSoon && session.refresh(), 3600000);
        },

        async loadConversations() {
            const res = await api.get('/conversations', { per_page: 100 });
            this.conversations = res.data;
        },

        async guestStart() {
            if (!this.guest.name.trim()) return;
            try {
                await session.guest(this.guest.name.trim(), this.guest.email.trim());
                await this.boot();
                if (!this.activeId && this.conversations[0]) await this.openConversation(this.conversations[0].id);
            } catch (e) {
                this.error = e.text || 'Не получилось начать чат';
            }
        },

        async logout() {
            await session.logout(api);
            realtime.disconnect();
            location.reload();
        },

        // ——— диалоги
        sortConversations() {
            this.conversations.sort((a, b) => (b.last_message_at || '').localeCompare(a.last_message_at || ''));
        },

        upsertConversation(c) {
            if (!c) return;
            const i = this.conversations.findIndex((x) => x.id === c.id);
            i >= 0 ? this.conversations.splice(i, 1, c) : this.conversations.push(c);
            this.sortConversations();
            this.scheduleUnread();
        },

        removeConversation(id) {
            this.conversations = this.conversations.filter((c) => c.id !== id);
            if (this.activeId === id) this.closeThread();
            this.scheduleUnread();
        },

        async openConversation(id) {
            if (!id) return;
            if (!this.conversations.some((c) => c.id === id)) {
                try {
                    this.upsertConversation((await api.get(`/conversations/${id}`)).data);
                } catch {
                    return;
                }
            }
            this.activeId = id;
            this.mobileThread = true;
            this.messages = [];
            this.typing = {};
            this.online = {};
            this.replyTo = this.editing = null;
            this.draft = '';
            this.loadingThread = true;
            bridge.emit('opened', { id });

            leavePresence?.();
            leavePresence = realtime.joinConversation(id, {
                here: (list) => (this.online = Object.fromEntries(list.map((p) => [p.id, true]))),
                joining: (p) => (this.online = { ...this.online, [p.id]: true }),
                leaving: (p) => {
                    const { [p.id]: _, ...rest } = this.online;
                    this.online = rest;
                },
                typing: (e) => this.onTyping(e),
            });

            try {
                const res = await api.get(`/conversations/${id}/messages`, { limit: PAGE });
                if (this.activeId !== id) return;
                this.messages = res.data;
                this.hasOlder = res.data.length >= PAGE;
            } catch (e) {
                this.flash(e.text);
            } finally {
                this.loadingThread = false;
            }
            this.$nextTick(() => {
                this.scrollDown(false);
                this.$refs.composer?.focus?.();
            });
            this.scheduleRead();
        },

        /** «Открыть диалог по заявке №123»: берём уже привязанный к контексту или заводим обращение в поддержку. */
        async openContext(ctx) {
            try {
                const found = await api.get('/conversations', { context_type: ctx.type, context_id: String(ctx.id), per_page: 1 });
                if (found.data[0]) {
                    this.upsertConversation(found.data[0]);

                    return this.openConversation(found.data[0].id);
                }
                if (!this.app?.support_enabled) return;
                const made = await api.post('/conversations', { type: 'support', context: { type: ctx.type, id: String(ctx.id), title: ctx.title, url: ctx.url } });
                this.upsertConversation(made.data);
                await this.openConversation(made.data.id);
            } catch (e) {
                this.flash(e.text);
            }
        },

        openFromHost(m) {
            if (m.id) this.openConversation(m.id);
            else if (m.context) this.openContext(m.context);
        },

        closeThread() {
            this.activeId = null;
            this.mobileThread = false;
            this.messages = [];
            leavePresence?.();
            leavePresence = null;
        },

        async newSupport() {
            try {
                const made = await api.post('/conversations', { type: 'support' });
                this.upsertConversation(made.data);
                await this.openConversation(made.data.id);
            } catch (e) {
                this.flash(e.text);
            }
        },

        async searchPeople() {
            if (this.peopleQuery.trim().length < 2) {
                this.people = [];

                return;
            }
            this.people = (await api.get('/participants', { q: this.peopleQuery.trim() })).data;
        },

        async startDirect(person) {
            try {
                const made = await api.post('/conversations', { type: 'direct', participant_id: person.id });
                this.peopleOpen = false;
                this.upsertConversation(made.data);
                await this.openConversation(made.data.id);
            } catch (e) {
                this.flash(e.text);
            }
        },

        // ——— сообщения
        async loadOlder() {
            if (this.loadingOlder || !this.hasOlder || !this.messages.length) return;
            this.loadingOlder = true;
            const box = this.$refs.thread;
            const before = box.scrollHeight;
            try {
                const res = await api.get(`/conversations/${this.activeId}/messages`, { limit: PAGE, before: this.messages[0].id });
                this.messages = [...res.data, ...this.messages];
                this.hasOlder = res.data.length >= PAGE;
                this.$nextTick(() => (box.scrollTop = box.scrollHeight - before));
            } finally {
                this.loadingOlder = false;
            }
        },

        onThreadScroll() {
            if (this.$refs.thread.scrollTop < 120) this.loadOlder();
        },

        scrollDown(smooth = true) {
            const box = this.$refs.thread;
            if (box) box.scrollTo({ top: box.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
        },

        nearBottom() {
            const box = this.$refs.thread;

            return !box || box.scrollHeight - box.scrollTop - box.clientHeight < 160;
        },

        uuid() {
            return crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2);
        },

        async send() {
            const body = this.draft.trim();
            if (!body || !this.activeId) return;

            if (this.editing) {
                const m = this.editing;
                this.editing = null;
                this.draft = '';
                this.autosize();
                try {
                    this.replace((await api.patch(`/conversations/${this.activeId}/messages/${m.id}`, { body })).data);
                } catch (e) {
                    this.flash(e.text);
                }

                return;
            }

            const pending = {
                id: null,
                client_uuid: this.uuid(),
                type: 'text',
                body,
                sender: this.me,
                reply_to: this.replyTo ? { id: this.replyTo.id, sender_name: this.replyTo.sender?.name, preview: this.replyTo.body || '' } : null,
                created_at: new Date().toISOString(),
                pending: true,
            };
            const convId = this.activeId;
            this.messages.push(pending);
            this.draft = '';
            const replyId = this.replyTo?.id;
            this.replyTo = null;
            this.autosize();
            this.$nextTick(() => this.scrollDown());

            await this.deliver(pending, () => api.post(`/conversations/${convId}/messages`, { body, client_uuid: pending.client_uuid, reply_to_id: replyId }));
        },

        async deliver(pending, request) {
            this.sending++;
            try {
                const saved = (await request()).data;
                const i = this.messages.findIndex((m) => m.client_uuid === pending.client_uuid);
                const dup = this.messages.findIndex((m) => m.id === saved.id);
                if (dup >= 0 && dup !== i) this.messages.splice(i, 1);          // событие realtime успело раньше ответа
                else if (i >= 0) this.messages.splice(i, 1, saved);
            } catch (e) {
                pending.pending = false;
                pending.failed = true;
                pending.retry = () => {
                    pending.failed = false;
                    pending.pending = true;
                    return this.deliver(pending, request);
                };
                this.flash(e.text || 'Не отправилось');
            } finally {
                this.sending--;
            }
        },

        async attach(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file || !this.activeId) return;
            const form = new FormData();
            form.append('file', file);
            form.append('client_uuid', this.uuid());
            const pending = { id: null, client_uuid: form.get('client_uuid'), type: 'file', sender: this.me, file: { name: file.name, size: file.size, kind: 'file', url: null }, created_at: new Date().toISOString(), pending: true };
            this.messages.push(pending);
            this.$nextTick(() => this.scrollDown());
            await this.deliver(pending, () => api.upload(`/conversations/${this.activeId}/messages/file`, form));
        },

        replace(message) {
            const i = this.messages.findIndex((m) => m.id === message.id);
            if (i >= 0) this.messages.splice(i, 1, message);
        },

        startEdit(m) {
            this.editing = m;
            this.replyTo = null;
            this.draft = m.body;
            this.$nextTick(() => {
                this.autosize();
                this.$refs.composer?.focus();
            });
        },

        startReply(m) {
            this.replyTo = m;
            this.editing = null;
            this.$refs.composer?.focus();
        },

        cancelCompose() {
            this.replyTo = this.editing = null;
            this.draft = '';
            this.autosize();
        },

        askRemove(m) {
            this.deleteTarget = m;
            this.$nextTick(() => this.$refs.deleteCancel?.focus());
        },

        async remove() {
            const m = this.deleteTarget;
            this.deleteTarget = null;
            if (!m) return;
            try {
                this.replace((await api.del(`/conversations/${this.activeId}/messages/${m.id}`)).data);
            } catch (e) {
                this.flash(e.text);
            }
        },

        async reveal(m) {
            try {
                const r = (await api.get(`/conversations/${this.activeId}/messages/${m.id}/reveal`)).data;
                m.revealed = r.body;
            } catch (e) {
                this.flash(e.text);
            }
        },

        // ——— голосовые
        async startRecording() {
            try {
                recorder = createRecorder();
                await recorder.start();
            } catch {
                this.flash('Нет доступа к микрофону (нужен HTTPS и разрешение браузера)');

                return;
            }
            this.recording = true;
            this.recSeconds = 0;
            recTimer = setInterval(() => {
                this.recSeconds++;
                if (this.recSeconds >= 300) this.finishRecording(true);
            }, 1000);
        },

        cancelRecording() {
            clearInterval(recTimer);
            recorder?.cancel();
            this.recording = false;
        },

        async finishRecording(send) {
            clearInterval(recTimer);
            const seconds = Math.max(1, this.recSeconds);
            const blob = await recorder.stop();
            this.recording = false;
            if (!send || !blob || !this.activeId) return;

            const ext = blob.type.includes('mp4') ? 'm4a' : blob.type.includes('ogg') ? 'ogg' : 'webm';
            const form = new FormData();
            form.append('audio', blob, `voice.${ext}`);
            form.append('duration', seconds);
            form.append('client_uuid', this.uuid());
            const pending = { id: null, client_uuid: form.get('client_uuid'), type: 'voice', sender: this.me, voice: { url: URL.createObjectURL(blob), duration: seconds }, created_at: new Date().toISOString(), pending: true };
            this.messages.push(pending);
            this.$nextTick(() => this.scrollDown());
            await this.deliver(pending, () => api.upload(`/conversations/${this.activeId}/messages/voice`, form));
        },

        playVoice(m, event) {
            const audio = event.currentTarget.closest('[data-voice]').querySelector('audio');
            audio.paused ? audio.play() : audio.pause();
        },

        // ——— события realtime
        onMessage(e, created) {
            const m = e.message;
            if (e.conversation) this.upsertConversation(e.conversation);

            if (m.conversation_id !== this.activeId) return;

            const i = this.messages.findIndex((x) => x.id === m.id || (m.client_uuid && x.client_uuid === m.client_uuid));
            if (i >= 0) {
                this.messages.splice(i, 1, m);
            } else if (created) {
                const stick = this.nearBottom() || m.sender?.id === this.me.id;
                this.messages.push(m);
                if (stick) this.$nextTick(() => this.scrollDown());
            }
            if (created && m.sender?.id !== this.me.id) {
                delete this.typing[m.sender?.id];
                this.scheduleRead();
            }
        },

        onRead(e) {
            const c = this.conversations.find((x) => x.id === e.conversation_id);
            const member = c?.members?.find((x) => x.id === e.participant_id);
            if (member) member.last_read_message_id = e.last_read_message_id;
        },

        onTyping(e) {
            if (e.participant_id === this.me.id) return;
            this.typing = { ...this.typing, [e.participant_id]: e.name };
            clearTimeout(typingTimers[e.participant_id]);
            typingTimers[e.participant_id] = setTimeout(() => {
                const { [e.participant_id]: _, ...rest } = this.typing;
                this.typing = rest;
            }, 4000);
        },

        onInput() {
            this.autosize();
            if (this.editing || !this.activeId || Date.now() - typingSentAt < 3000 || !this.draft) return;
            typingSentAt = Date.now();
            api.post(`/conversations/${this.activeId}/typing`).catch(() => {});
        },

        async onConnection(s) {
            const up = s.current === 'connected';
            if (up === this.connected) return;
            this.connected = up;
            if (!up) return;

            // Связь вернулась: подтягиваем всё, что пропустили.
            try {
                await this.loadConversations();
                if (this.activeId && this.messages.length) {
                    const last = [...this.messages].reverse().find((m) => m.id)?.id ?? 0;
                    const res = await api.get(`/conversations/${this.activeId}/messages`, { after: last, limit: 100 });
                    for (const m of res.data) if (!this.messages.some((x) => x.id === m.id)) this.messages.push(m);
                    this.$nextTick(() => this.scrollDown());
                }
                this.scheduleRead();
                this.refreshUnread();
            } catch {
                /* повторим при следующем переподключении */
            }
        },

        // ——— прочитано и счётчики
        canRead() {
            return this.phase === 'ready' && this.activeId && document.visibilityState === 'visible' && this.hostVisible;
        },

        scheduleRead() {
            clearTimeout(readTimer);
            readTimer = setTimeout(() => this.markRead(), 350);
        },

        async markRead() {
            const c = this.active;
            if (!c || !this.canRead() || !c.unread_count) return;
            const last = [...this.messages].reverse().find((m) => m.id)?.id;
            if (!last) return;
            c.unread_count = 0;
            try {
                await api.post(`/conversations/${c.id}/read`, { message_id: last });
            } catch {
                /* при следующем событии попробуем снова */
            }
            this.scheduleUnread();
        },

        scheduleUnread() {
            clearTimeout(unreadTimer);
            unreadTimer = setTimeout(() => this.refreshUnread(), 400);
        },

        async refreshUnread() {
            try {
                this.unreadTotal = (await api.get('/unread')).data.total;
            } catch {
                this.unreadTotal = this.conversations.reduce((n, c) => n + c.unread_count, 0);
            }
            bridge.emit('unread', { total: this.unreadTotal });
            if (!bridge.embedded && this.app) document.title = (this.unreadTotal ? `(${this.unreadTotal}) ` : '') + (this.app.title || 'Чат');
        },

        isRead(m) {
            const c = this.active;

            return !!c && m.id && c.members.some((x) => x.id !== this.me.id && (x.last_read_message_id || 0) >= m.id);
        },

        // ——— push
        async checkPush() {
            if (bridge.embedded || !cfg.vapid || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                this.pushState = 'unsupported';

                return;
            }
            const reg = await navigator.serviceWorker.getRegistration('/sw.js');
            this.pushState = (await reg?.pushManager.getSubscription()) ? 'on' : 'off';
        },

        async enablePush() {
            const b64 = cfg.vapid.replace(/-/g, '+').replace(/_/g, '/');
            const key = Uint8Array.from(atob(b64 + '='.repeat((4 - (b64.length % 4)) % 4)), (c) => c.charCodeAt(0));
            try {
                const reg = await navigator.serviceWorker.register('/sw.js');
                if ((await Notification.requestPermission()) !== 'granted') return this.flash('Уведомления запрещены в браузере');
                const sub = (await reg.pushManager.getSubscription()) || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key }));
                await api.post('/push/subscribe', sub.toJSON());
                this.pushState = 'on';
            } catch (e) {
                this.flash(e.text || 'Не удалось включить уведомления');
            }
        },

        // ——— вспомогательное
        autosize() {
            this.$nextTick(() => {
                const el = this.$refs.composer;
                if (!el) return;
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 140) + 'px';
            });
        },

        onKey(e) {
            const touch = window.matchMedia('(pointer: coarse)').matches;
            if (e.key === 'Enter' && !e.shiftKey && !touch && !e.isComposing) {
                e.preventDefault();
                this.send();
            }
            if (e.key === 'Escape') this.cancelCompose();
        },

        flash(text) {
            this.toast = text || 'Ошибка';
            clearTimeout(this._toast);
            this._toast = setTimeout(() => (this.toast = ''), 4000);
        },

        mine(m) {
            return m.sender?.id === this.me?.id;
        },
        preview(c) {
            const m = c.last_message;
            if (!m) return 'Нет сообщений';
            const who = m.sender?.id === this.me?.id ? 'Вы: ' : '';
            if (m.deleted) return 'Сообщение удалено';
            if (m.type === 'voice') return who + 'Голосовое сообщение';
            if (m.type === 'file') return who + (m.file?.kind === 'image' ? 'Фото' : m.file?.kind === 'video' ? 'Видео' : 'Файл: ' + (m.file?.name || ''));

            return who + (m.body || '');
        },

        // форматтеры для шаблона
        linkify,
        formatTime,
        listTime,
        formatDuration,
        formatFileSize,
        initials,
        avatarColor,
    };
}

window.Alpine = Alpine;
Alpine.data('chatApp', chatApp);
Alpine.start();
