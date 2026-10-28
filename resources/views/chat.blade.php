<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Чат' }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230f766e'%3E%3Cpath d='M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z'/%3E%3C/svg%3E">
    <script>window.CHAT = @json($config);</script>
    @vite(['resources/js/app.js'])
</head>
<body>
<svg width="0" height="0" class="absolute" aria-hidden="true">
    <defs>
        <symbol id="i-send" viewBox="0 0 24 24"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.3 10l12.6 2-12.6 2z"/></symbol>
        <symbol id="i-clip" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m21 11.5-8.6 8.6a5.5 5.5 0 0 1-7.8-7.8l8.6-8.6a3.7 3.7 0 0 1 5.2 5.2l-8.6 8.6a1.8 1.8 0 0 1-2.6-2.6l7.9-7.9"/></symbol>
        <symbol id="i-mic" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3zM6 11a6 6 0 0 0 12 0M12 17v4"/></symbol>
        <symbol id="i-back" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m15 5-7 7 7 7"/></symbol>
        <symbol id="i-x" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5"/></symbol>
        <symbol id="i-checks" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="m2 12.5 4.5 4.5L16 7.5M10 15.5l1.5 1.5L21 7.5"/></symbol>
        <symbol id="i-reply" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 8 4 13l5 5M4 13h10a6 6 0 0 1 6 6v0"/></symbol>
        <symbol id="i-edit" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m4 20 4-1L19.5 7.5a2.1 2.1 0 0 0-3-3L5 16l-1 4zM14 6l4 4"/></symbol>
        <symbol id="i-dots" viewBox="0 0 24 24"><path fill="currentColor" d="M12 7a1.6 1.6 0 1 0 0-3.2A1.6 1.6 0 0 0 12 7zm0 6.6a1.6 1.6 0 1 0 0-3.2 1.6 1.6 0 0 0 0 3.2zm0 6.6a1.6 1.6 0 1 0 0-3.2 1.6 1.6 0 0 0 0 3.2z"/></symbol>
        <symbol id="i-pin" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m15 4 5 5-3 1-3.5 3.5.5 4-1.5 1.5-4-4L4 20.5M9 9l-.5 3.5L14 18M14.5 3.5 20.5 9.5"/></symbol>
        <symbol id="i-forward" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m15 5 5 5-5 5M20 10H9a5 5 0 0 0-5 5v4"/></symbol>
        <symbol id="i-copy" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 9h10v11H9zM5 15V4h10"/></symbol>
        <symbol id="i-share" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3v12M7.5 7.5 12 3l4.5 4.5M5 12v8h14v-8"/></symbol>
        <symbol id="i-trash" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></symbol>
        <symbol id="i-play" viewBox="0 0 24 24"><path fill="currentColor" d="M7 4.5v15l13-7.5z"/></symbol>
        <symbol id="i-pause" viewBox="0 0 24 24"><path fill="currentColor" d="M6 4h4v16H6zM14 4h4v16h-4z"/></symbol>
        <symbol id="i-file" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M6 3h8l5 5v13H6zM14 3v5h5"/></symbol>
        <symbol id="i-plus" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M12 5v14M5 12h14"/></symbol>
        <symbol id="i-bell" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7zM10 20a2 2 0 0 0 4 0"/></symbol>
        <symbol id="i-link" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></symbol>
    </defs>
</svg>

<div x-data="chatApp" x-cloak class="relative flex h-dvh w-full flex-col overflow-hidden bg-white">

    {{-- Состояния до входа --}}
    <template x-if="phase === 'loading'">
        <div class="m-auto flex flex-col items-center gap-3 text-sm text-slate-500" role="status">
            <span class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-accent"></span>
            Подключаемся к чату…
        </div>
    </template>

    <template x-if="phase === 'error'">
        <div class="m-auto max-w-sm px-6 text-center">
            <p class="text-base font-semibold text-slate-800">Не удалось открыть чат</p>
            <p class="mt-2 text-sm text-slate-500" x-text="error"></p>
            <button type="button" class="mt-4 rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-dark" @click="location.reload()">Повторить</button>
        </div>
    </template>

    <template x-if="phase === 'need-login'">
        <div class="m-auto max-w-sm px-6 text-center">
            <p class="text-base font-semibold text-slate-800">Войдите на сайте</p>
            <template x-if="canLoginViaSite">
                <div>
                    <p class="mt-2 text-sm text-slate-500">Вход проходит через ваш личный кабинет: он подтвердит, кто вы, и вернёт вас сюда.</p>
                    <p x-show="error" x-text="error" class="mt-3 text-sm text-red-600"></p>
                    <button type="button" class="mt-5 w-full rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-white hover:bg-accent-dark" @click="loginViaSite()" x-text="loginLabel"></button>
                </div>
            </template>
            <template x-if="!canLoginViaSite">
                <p class="mt-2 text-sm text-slate-500">Чат открывается из вашего личного кабинета. Вернитесь в кабинет и нажмите «Чат», либо откройте личную ссылку, которую вам выдали.</p>
            </template>
        </div>
    </template>

    <template x-if="phase === 'guest-form'">
        <form class="m-auto w-full max-w-sm px-6" @submit.prevent="guestStart()">
            <template x-if="canLoginViaSite">
                <div class="mb-6 border-b border-slate-200 pb-6">
                    <button type="button" class="w-full rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-white hover:bg-accent-dark" @click="loginViaSite()" x-text="loginLabel"></button>
                    <p class="mt-2 text-center text-xs text-slate-400">или напишите нам без входа</p>
                </div>
            </template>
            <p class="text-lg font-semibold text-slate-800">Написать нам</p>
            <p class="mt-1 text-sm text-slate-500">Представьтесь — и откроем диалог. Он сохранится по вашей личной ссылке.</p>
            <label class="mt-5 block text-sm font-medium text-slate-700" for="g-name">Ваше имя</label>
            <input id="g-name" x-model="guest.name" required maxlength="120" autocomplete="name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-accent focus:ring-2 focus:ring-accent-mid">
            <label class="mt-3 block text-sm font-medium text-slate-700" for="g-mail">E-mail <span class="font-normal text-slate-400">— необязательно</span></label>
            <input id="g-mail" type="email" x-model="guest.email" maxlength="190" autocomplete="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-accent focus:ring-2 focus:ring-accent-mid">
            <p x-show="error" x-text="error" class="mt-3 text-sm text-red-600"></p>
            <button class="mt-5 w-full rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-white hover:bg-accent-dark">Начать чат</button>
        </form>
    </template>

    {{-- Основной интерфейс --}}
    <template x-if="phase === 'ready'">
        <div class="flex min-h-0 flex-1">

            {{-- Список диалогов --}}
            <aside x-show="layout === 'full'" :class="mobileThread ? 'hidden md:flex' : 'flex'"
                   class="w-full shrink-0 flex-col border-r border-slate-200 bg-white md:w-80 lg:w-96" aria-label="Диалоги">
                <header class="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
                    <h1 class="min-w-0 flex-1 truncate text-lg font-semibold" x-text="app.title || 'Чат'"></h1>
                    <span x-show="unreadTotal" x-text="unreadTotal" class="rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-white" title="Непрочитанных"></span>
                    <button type="button" x-show="pushState === 'off'" @click="enablePush()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Включить уведомления" aria-label="Включить уведомления">
                        <svg class="h-5 w-5"><use href="#i-bell"/></svg>
                    </button>
                    <button type="button" x-show="app.support_enabled" @click="newSupport()" class="rounded-lg p-2 text-accent hover:bg-accent-soft" title="Написать в поддержку" aria-label="Написать в поддержку">
                        <svg class="h-5 w-5"><use href="#i-plus"/></svg>
                    </button>
                    <button type="button" x-show="app.user_conversations && app.directory" @click="peopleOpen = true" class="rounded-lg p-2 text-accent hover:bg-accent-soft" title="Новый диалог" aria-label="Новый диалог">
                        <svg class="h-5 w-5"><use href="#i-plus"/></svg>
                    </button>
                </header>

                <div class="flex items-center gap-2 border-b border-slate-100 px-3 py-2">
                    <input type="search" x-model="search" placeholder="Поиск" aria-label="Поиск по диалогам" class="min-w-0 flex-1 rounded-lg bg-slate-100 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-accent-mid">
                    <button type="button" @click="onlyUnread = !onlyUnread" :class="onlyUnread ? 'bg-accent text-white' : 'bg-slate-100 text-slate-600'" class="rounded-lg px-3 py-2 text-xs font-medium" :aria-pressed="onlyUnread">Новые</button>
                </div>

                <ul class="lw-scroll min-h-0 flex-1 overflow-y-auto" role="list">
                    <template x-for="c in filtered" :key="c.id">
                        <li>
                            <button type="button" @click="openConversation(c.id)" :class="c.id === activeId ? 'bg-accent-soft' : 'hover:bg-slate-50'"
                                    class="flex w-full items-center gap-3 px-4 py-3 text-left">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white" :style="`background:${avatarColor(c.title)}`" x-text="initials(c.title)"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-baseline gap-2">
                                        <span class="min-w-0 flex-1 truncate text-sm font-semibold" x-text="c.title"></span>
                                        <svg x-show="c.pinned" class="h-3.5 w-3.5 shrink-0 text-slate-400" role="img" aria-label="Закреплён"><use href="#i-pin"/></svg>
                                        <span class="shrink-0 text-xs text-slate-400" x-text="listTime(c.last_message?.created_at || c.last_message_at)"></span>
                                    </span>
                                    <span class="mt-0.5 flex items-center gap-2">
                                        <span class="min-w-0 flex-1 truncate text-sm" :class="c.unread_count ? 'font-medium text-slate-800' : 'text-slate-500'" x-text="preview(c)"></span>
                                        <span x-show="c.unread_count" x-text="c.unread_count > 99 ? '99+' : c.unread_count" class="min-w-5 shrink-0 rounded-full bg-accent px-1.5 text-center text-xs font-semibold leading-5 text-white"></span>
                                    </span>
                                </span>
                            </button>
                        </li>
                    </template>
                    <li x-show="!filtered.length" class="px-6 py-10 text-center text-sm text-slate-500">
                        <span x-show="!conversations.length">Пока нет диалогов.</span>
                        <span x-show="conversations.length">Ничего не найдено.</span>
                    </li>
                </ul>

                <footer x-show="!embedded && me" class="flex items-center gap-2 border-t border-slate-200 px-4 py-2 text-xs text-slate-500">
                    <span class="min-w-0 flex-1 truncate" x-text="me.name + (me.role ? ' · ' + me.role : '')"></span>
                    <button type="button" class="hover:text-slate-800" @click="logout()">Выйти</button>
                </footer>
            </aside>

            {{-- Диалог --}}
            <section :class="(layout === 'thread' || mobileThread) ? 'flex' : 'hidden md:flex'" class="min-w-0 flex-1 flex-col" aria-label="Переписка">

                <div x-show="!active && !loadingThread" class="lw-thread-bg m-0 flex flex-1 flex-col items-center justify-center px-6 text-center">
                    <svg class="h-12 w-12 text-accent/40"><use href="#i-send"/></svg>
                    <p class="mt-3 text-sm text-slate-500" x-text="conversations.length ? 'Выберите диалог слева' : (app.support_enabled ? 'Напишите нам — ответим здесь' : 'Диалогов пока нет')"></p>
                    <button type="button" x-show="app.support_enabled && !conversations.length" @click="newSupport()" class="mt-4 rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-dark">Написать в поддержку</button>
                </div>

                <template x-if="active">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <header class="relative flex items-center gap-3 border-b border-slate-200 bg-white px-3 py-2.5">
                            <button type="button" x-show="layout === 'full'" @click="closeThread()" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" aria-label="К списку диалогов"><svg class="h-5 w-5"><use href="#i-back"/></svg></button>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white" :style="`background:${avatarColor(active.title)}`" x-text="initials(active.title)"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold" x-text="active.title"></p>
                                <p class="truncate text-xs text-slate-500">
                                    <span x-show="typingNames.length" class="lw-typing text-accent"><i></i><i></i><i></i> <span x-text="typingNames.join(', ') + (typingNames.length > 1 ? ' печатают' : ' печатает')"></span></span>
                                    <span x-show="!typingNames.length">
                                        <span x-show="othersOnline" class="text-emerald-600">в сети</span><span x-show="othersOnline && headerSub"> · </span><span x-text="headerSub"></span>
                                    </span>
                                </p>
                            </div>
                            <a x-show="active.context?.url" :href="active.context?.url" target="_blank" rel="noopener" class="hidden shrink-0 items-center gap-1 rounded-lg bg-accent-soft px-2.5 py-1.5 text-xs font-medium text-accent-dark hover:bg-accent-mid sm:flex" :title="active.context?.title">
                                <svg class="h-4 w-4"><use href="#i-link"/></svg><span class="max-w-40 truncate" x-text="active.context?.title || 'Открыть'"></span>
                            </a>
                            <div class="relative shrink-0" @click.outside="convMenu = false" @keydown.escape.window="convMenu = false">
                                <button type="button" @click="convMenu = !convMenu" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Действия с диалогом" aria-haspopup="menu" :aria-expanded="convMenu"><svg class="h-5 w-5"><use href="#i-dots"/></svg></button>
                                <div x-show="convMenu" x-transition.opacity role="menu" class="absolute right-0 top-full z-30 mt-1 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                    <button type="button" role="menuitem" @click="togglePin(active)" class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50"><svg class="h-4 w-4 text-slate-400"><use href="#i-pin"/></svg><span x-text="active.pinned ? 'Открепить' : 'Закрепить'"></span></button>
                                    <button type="button" role="menuitem" @click="askDeleteConversation('me')" class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50"><svg class="h-4 w-4 text-slate-400"><use href="#i-trash"/></svg>Удалить для себя</button>
                                    <button type="button" role="menuitem" x-show="active.can_delete_for_all" @click="askDeleteConversation('all')" class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm text-red-600 hover:bg-red-50"><svg class="h-4 w-4"><use href="#i-trash"/></svg>Удалить навсегда</button>
                                </div>
                            </div>
                        </header>

                        <div x-show="!connected" class="bg-amber-50 px-4 py-1.5 text-center text-xs text-amber-800" role="status">Нет связи — переподключаемся…</div>

                        <div x-ref="thread" @scroll.passive="onThreadScroll()" class="lw-scroll lw-thread-bg min-h-0 flex-1 overflow-y-auto px-3 py-3 sm:px-5" role="log" aria-live="polite">
                            <p x-show="loadingOlder || loadingThread" class="py-2 text-center text-xs text-slate-400">Загружаем…</p>
                            <p x-show="!hasOlder && !loadingThread && messages.length" class="py-2 text-center text-xs text-slate-400">Начало переписки</p>

                            <template x-for="it in items" :key="it.key">
                                <div>
                                    <p x-show="it.kind === 'day'" class="my-3 text-center"><span class="rounded-full bg-white/80 px-3 py-1 text-xs text-slate-500 shadow-sm" x-text="it.label"></span></p>

                                    <template x-if="it.kind === 'msg' && it.m.type === 'system'">
                                        <p class="my-2 text-center"><span class="inline-block max-w-[90%] rounded-lg bg-white/80 px-3 py-1.5 text-xs text-slate-600 shadow-sm" x-text="it.m.body"></span></p>
                                    </template>

                                    <template x-if="it.kind === 'msg' && it.m.type !== 'system'">
                                        <div class="group flex gap-2" :class="[mine(it.m) ? 'justify-end' : 'justify-start', it.first ? 'mt-3' : 'mt-0.5']">
                                            <span x-show="!mine(it.m) && active.type !== 'direct'" class="mt-auto h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white" :class="it.first ? 'flex' : 'invisible flex'" :style="`background:${avatarColor(it.m.sender?.name || '?')}`" x-text="initials(it.m.sender?.name)"></span>

                                            <div class="lw-bubble relative rounded-2xl px-3 py-1.5 shadow-sm" :class="mine(it.m) ? 'rounded-br-md bg-accent-mid' : 'rounded-bl-md bg-white'">
                                                <p x-show="it.first && !mine(it.m) && active.type !== 'direct'" class="text-xs font-semibold" :style="`color:${avatarColor(it.m.sender?.name || '?')}`">
                                                    <span x-text="it.m.sender?.name"></span><span x-show="it.m.sender?.role" class="font-normal text-slate-400" x-text="' · ' + it.m.sender?.role"></span>
                                                </p>

                                                <p x-show="it.m.forwarded_from" class="text-xs italic text-accent-dark">Переслано от <span class="font-semibold not-italic" x-text="it.m.forwarded_from"></span></p>

                                                <div x-show="it.m.reply_to" class="my-1 rounded-md border-l-4 border-accent bg-black/5 px-2 py-1 text-xs">
                                                    <p class="font-semibold text-accent-dark" x-text="it.m.reply_to?.sender_name"></p>
                                                    <p class="line-clamp-2 text-slate-600" x-text="it.m.reply_to?.preview"></p>
                                                </div>

                                                {{-- удалено --}}
                                                <p x-show="it.m.deleted" class="py-0.5 text-sm italic text-slate-400">
                                                    Сообщение удалено
                                                    <template x-if="me.is_operator && !it.m.revealed"><button type="button" class="ml-1 not-italic underline" @click="reveal(it.m)">показать</button></template>
                                                </p>
                                                <p x-show="it.m.deleted && it.m.revealed" class="whitespace-pre-wrap border-t border-dashed border-slate-300 pt-1 text-sm text-slate-500" x-text="it.m.revealed"></p>

                                                {{-- текст --}}
                                                <p x-show="!it.m.deleted && it.m.type === 'text'" class="whitespace-pre-wrap py-0.5 text-sm leading-snug" x-html="linkify(it.m.body)"></p>
                                                <details x-show="it.m.original_body" class="text-xs text-slate-500"><summary class="cursor-pointer select-none">изменено</summary><p class="mt-1 whitespace-pre-wrap">было: <span x-text="it.m.original_body"></span></p></details>

                                                {{-- голосовое --}}
                                                <template x-if="!it.m.deleted && it.m.type === 'voice' && it.m.voice">
                                                    <div data-voice class="flex w-56 items-center gap-3 py-1" x-data="{ playing: false, p: 0 }">
                                                        <button type="button" @click="playVoice(it.m, $event)" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-accent text-white" :aria-label="playing ? 'Пауза' : 'Слушать'">
                                                            <svg class="h-4 w-4"><use :href="playing ? '#i-pause' : '#i-play'"/></svg>
                                                        </button>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="h-1.5 overflow-hidden rounded-full bg-black/10"><div class="h-full bg-accent" :style="`width:${p}%`"></div></div>
                                                            <p class="mt-1 text-xs text-slate-500" x-text="formatDuration(it.m.voice.duration)"></p>
                                                        </div>
                                                        <audio preload="none" :src="it.m.voice.url" class="hidden"
                                                               @play="playing = true" @pause="playing = false" @ended="playing = false; p = 0"
                                                               @timeupdate="p = $event.target.duration ? $event.target.currentTime / $event.target.duration * 100 : 0"></audio>
                                                    </div>
                                                </template>

                                                {{-- файл --}}
                                                <template x-if="!it.m.deleted && it.m.type === 'file' && it.m.file">
                                                    <div class="py-0.5">
                                                        <a x-show="it.m.file.kind === 'image' && it.m.file.url" :href="it.m.file.url" target="_blank" rel="noopener"><img :src="it.m.file.url" :alt="it.m.file.name" loading="lazy" class="max-h-64 max-w-full rounded-lg"></a>
                                                        <video x-show="it.m.file.kind === 'video' && it.m.file.url" :src="it.m.file.url" controls preload="metadata" class="max-h-64 max-w-full rounded-lg"></video>
                                                        <a x-show="it.m.file.kind === 'file'" :href="it.m.file.url || '#'" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-lg bg-black/5 px-3 py-2 hover:bg-black/10">
                                                            <svg class="h-6 w-6 shrink-0 text-accent"><use href="#i-file"/></svg>
                                                            <span class="min-w-0"><span class="block max-w-48 truncate text-sm font-medium" x-text="it.m.file.name"></span><span class="block text-xs text-slate-500" x-text="formatFileSize(it.m.file.size)"></span></span>
                                                        </a>
                                                    </div>
                                                </template>

                                                <p class="mt-0.5 flex items-center justify-end gap-1 text-[11px] text-slate-400">
                                                    <span x-text="formatTime(it.m.created_at)"></span>
                                                    <template x-if="mine(it.m) && !it.m.deleted">
                                                        <span class="inline-flex" :class="isRead(it.m) ? 'text-sky-500' : ''" :title="it.m.failed ? 'Не отправлено' : (isRead(it.m) ? 'Прочитано' : 'Доставлено')">
                                                            <svg x-show="it.m.pending" class="h-3.5 w-3.5 animate-pulse"><use href="#i-check"/></svg>
                                                            <svg x-show="!it.m.pending && !it.m.failed && !isRead(it.m)" class="h-3.5 w-3.5"><use href="#i-check"/></svg>
                                                            <svg x-show="!it.m.pending && !it.m.failed && isRead(it.m)" class="h-3.5 w-3.5"><use href="#i-checks"/></svg>
                                                        </span>
                                                    </template>
                                                </p>
                                                <button type="button" x-show="it.m.failed" @click="it.m.retry()" class="text-xs font-medium text-red-600 underline">Не отправлено — повторить</button>
                                            </div>

                                            <div x-show="it.m.id && !it.m.deleted" class="relative flex items-center self-center opacity-0 transition group-hover:opacity-100 focus-within:opacity-100 [@media(pointer:coarse)]:opacity-60" :class="msgMenu === it.m.id ? '!opacity-100' : ''" @click.outside="msgMenu === it.m.id && (msgMenu = null)" @keydown.escape="msgMenu = null">
                                                <button type="button" @click="startReply(it.m)" class="rounded-md p-1.5 text-slate-400 hover:bg-white hover:text-slate-700" title="Ответить" aria-label="Ответить"><svg class="h-4 w-4"><use href="#i-reply"/></svg></button>
                                                <button type="button" x-show="it.m.type === 'text'" @click="copyMessage(it.m)" class="rounded-md p-1.5 text-slate-400 hover:bg-white hover:text-slate-700" title="Копировать текст" aria-label="Копировать текст"><svg class="h-4 w-4"><use href="#i-copy"/></svg></button>
                                                <button type="button" x-show="canForward(it.m)" @click="askForward(it.m)" class="rounded-md p-1.5 text-slate-400 hover:bg-white hover:text-slate-700" title="Переслать" aria-label="Переслать"><svg class="h-4 w-4"><use href="#i-forward"/></svg></button>
                                                <button type="button" x-show="mine(it.m) && it.m.type !== 'system'" @click="msgMenu = msgMenu === it.m.id ? null : it.m.id" class="rounded-md p-1.5 text-slate-400 hover:bg-white hover:text-slate-700" title="Ещё" aria-label="Ещё" aria-haspopup="menu" :aria-expanded="msgMenu === it.m.id"><svg class="h-4 w-4"><use href="#i-dots"/></svg></button>
                                                <div x-show="msgMenu === it.m.id" x-transition.opacity role="menu" class="absolute right-0 top-full z-20 mt-1 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 text-left shadow-xl">
                                                    <button type="button" role="menuitem" @click="openShare(it.m)" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50"><svg class="h-4 w-4 text-slate-400"><use href="#i-share"/></svg>Поделиться</button>
                                                    <button type="button" role="menuitem" x-show="it.m.type === 'text' && !it.m.forwarded_from" @click="msgMenu = null; startEdit(it.m)" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50"><svg class="h-4 w-4 text-slate-400"><use href="#i-edit"/></svg>Изменить</button>
                                                    <button type="button" role="menuitem" @click="msgMenu = null; askRemove(it.m)" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-sm text-red-600 hover:bg-red-50"><svg class="h-4 w-4"><use href="#i-trash"/></svg>Удалить</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Поле ввода --}}
                        <div class="border-t border-slate-200 bg-white px-3 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2">
                            <div x-show="replyTo || editing" class="mb-2 flex items-start gap-2 rounded-lg bg-accent-soft px-3 py-1.5 text-xs">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-accent-dark" x-text="editing ? 'Редактирование' : 'Ответ: ' + (replyTo?.sender?.name || '')"></p>
                                    <p class="truncate text-slate-600" x-text="(editing || replyTo)?.body || (replyTo?.type === 'voice' ? 'Голосовое сообщение' : 'Вложение')"></p>
                                </div>
                                <button type="button" @click="cancelCompose()" class="text-slate-400 hover:text-slate-700" aria-label="Отменить"><svg class="h-4 w-4"><use href="#i-x"/></svg></button>
                            </div>

                            <div x-show="recording" class="flex items-center gap-3 py-1.5">
                                <button type="button" @click="cancelRecording()" class="rounded-full p-2 text-slate-500 hover:bg-slate-100" aria-label="Отменить запись"><svg class="h-5 w-5"><use href="#i-trash"/></svg></button>
                                <span class="lw-rec-dot h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                <span class="flex-1 text-sm tabular-nums text-slate-700" x-text="formatDuration(recSeconds)"></span>
                                <button type="button" @click="finishRecording(true)" class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-white hover:bg-accent-dark" aria-label="Отправить голосовое"><svg class="h-5 w-5"><use href="#i-send"/></svg></button>
                            </div>

                            <div x-show="!recording" class="flex items-end gap-1.5">
                                <label class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" title="Прикрепить файл">
                                    <svg class="h-5 w-5"><use href="#i-clip"/></svg>
                                    <input type="file" class="sr-only" @change="attach($event)" aria-label="Прикрепить файл">
                                </label>
                                <textarea x-ref="composer" x-model="draft" @input="onInput()" @keydown="onKey($event)" rows="1" maxlength="4000" placeholder="Сообщение" aria-label="Сообщение"
                                          class="max-h-36 min-h-10 flex-1 resize-none rounded-2xl bg-slate-100 px-4 py-2.5 text-sm leading-snug outline-none focus:ring-2 focus:ring-accent-mid"></textarea>
                                <button type="button" x-show="draft.trim()" @click="send()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent text-white hover:bg-accent-dark" :aria-label="editing ? 'Сохранить' : 'Отправить'">
                                    <svg class="h-5 w-5"><use :href="editing ? '#i-check' : '#i-send'"/></svg>
                                </button>
                                <button type="button" x-show="!draft.trim()" @click="startRecording()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" title="Голосовое сообщение" aria-label="Записать голосовое"><svg class="h-5 w-5"><use href="#i-mic"/></svg></button>
                            </div>
                        </div>
                    </div>
                </template>
            </section>

            {{-- Подтверждение удаления --}}
            <div x-show="deleteTarget" x-transition.opacity class="absolute inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4"
                 @keydown.escape.window="deleteTarget = null" @click.self="deleteTarget = null">
                <div class="w-full max-w-xs rounded-2xl bg-white p-5 shadow-2xl" role="alertdialog" aria-modal="true" aria-labelledby="del-title" aria-describedby="del-text">
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-red-600"><svg class="h-6 w-6"><use href="#i-trash"/></svg></div>
                    <h2 id="del-title" class="mt-3 text-center text-base font-semibold text-slate-900">Удалить сообщение?</h2>
                    <p id="del-text" class="mt-1.5 text-center text-sm leading-snug text-slate-500">Собеседники увидят на его месте пометку «Сообщение удалено».</p>
                    <div class="mt-5 grid grid-cols-2 gap-2">
                        <button type="button" x-ref="deleteCancel" @click="deleteTarget = null" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Отмена</button>
                        <button type="button" @click="remove()" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700" data-confirm-delete>Удалить</button>
                    </div>
                </div>
            </div>

            {{-- Подтверждение удаления диалога --}}
            <div x-show="convDelete" x-transition.opacity class="absolute inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4"
                 @keydown.escape.window="convDelete = null" @click.self="convDelete = null">
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl" role="alertdialog" aria-modal="true" aria-labelledby="cdel-title" aria-describedby="cdel-text">
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-red-600"><svg class="h-6 w-6"><use href="#i-trash"/></svg></div>
                    <h2 id="cdel-title" class="mt-3 text-center text-base font-semibold text-slate-900" x-text="convDelete === 'all' ? 'Удалить диалог навсегда?' : 'Удалить диалог для себя?'"></h2>
                    <p id="cdel-text" class="mt-1.5 text-center text-sm leading-snug text-slate-500" x-show="convDelete === 'me'">Диалог пропадёт из вашего списка, у остальных останется. Если вам напишут снова, он вернётся, но без прежней переписки.</p>
                    <p class="mt-1.5 text-center text-sm leading-snug text-slate-500" x-show="convDelete === 'all'">Диалог исчезнет у всех участников, вернуть его из чата будет нельзя.<span x-show="active?.context"> Он привязан к «<span x-text="active?.context?.title || active?.context?.id"></span>»: новое обращение откроет пустой диалог.</span></p>
                    <div class="mt-5 grid grid-cols-2 gap-2">
                        <button type="button" x-ref="convDeleteCancel" @click="convDelete = null" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Отмена</button>
                        <button type="button" @click="deleteConversation()" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700" data-confirm-delete-conversation x-text="convDelete === 'all' ? 'Удалить навсегда' : 'Удалить'"></button>
                    </div>
                </div>
            </div>

            {{-- Переслать сообщение --}}
            <template x-if="fwd"><div class="absolute inset-0 z-40 flex items-start justify-center bg-slate-900/50 p-4 pt-16" @keydown.escape.window="fwd = null" @click.self="fwd = null">
                <div class="flex max-h-[80vh] w-full max-w-sm flex-col rounded-2xl bg-white p-4 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="fwd-title">
                    <h2 id="fwd-title" class="text-base font-semibold text-slate-900">Переслать в…</h2>
                    <p class="mt-1 truncate rounded-lg bg-slate-50 px-3 py-1.5 text-xs text-slate-500" x-text="fwd.message.body || (fwd.message.file ? fwd.message.file.name : 'Голосовое сообщение')"></p>
                    <input x-model="fwd.query" x-init="$nextTick(() => $el.focus())" placeholder="Поиск диалога" aria-label="Поиск диалога" class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-accent">
                    <ul class="lw-scroll mt-2 min-h-0 flex-1 overflow-y-auto">
                        <template x-for="c in fwdList" :key="c.id">
                            <li><label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                                <input type="checkbox" :value="c.id" x-model="fwd.selected" class="h-4 w-4 accent-[var(--accent)]">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white" :style="`background:${avatarColor(c.title)}`" x-text="initials(c.title)"></span>
                                <span class="min-w-0 flex-1 truncate text-sm" x-text="c.title"></span>
                            </label></li>
                        </template>
                        <li x-show="!fwdList.length" class="px-2 py-6 text-center text-sm text-slate-500">Ничего не найдено</li>
                    </ul>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button type="button" @click="fwd = null" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Отмена</button>
                        <button type="button" @click="forward()" :disabled="!fwd || !fwd.selected.length || fwd.sending" class="rounded-xl bg-accent px-4 py-2.5 text-sm font-semibold text-white hover:bg-accent-dark disabled:opacity-40" x-text="fwd && fwd.selected.length ? 'Переслать (' + fwd.selected.length + ')' : 'Переслать'"></button>
                    </div>
                </div>
            </div>

            </template>

            {{-- Поделиться сообщением --}}
            <template x-if="shareDlg"><div class="absolute inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4" @keydown.escape.window="shareDlg = null" @click.self="shareDlg = null">
                <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="share-title">
                    <h2 id="share-title" class="text-base font-semibold text-slate-900">Поделиться сообщением</h2>
                    <p class="mt-1 truncate rounded-lg bg-slate-50 px-3 py-1.5 text-xs text-slate-500" x-text="shareDlg && (shareDlg.message.body || (shareDlg.message.file ? shareDlg.message.file.name : 'Голосовое сообщение'))"></p>
                    <p x-show="shareDlg && shareDlg.loading" class="py-6 text-center text-sm text-slate-400">Загружаем…</p>

                    <div x-show="shareDlg && !shareDlg.loading">
                        <div x-show="shareDlg && shareDlg.link" class="mt-4">
                            <label class="text-xs font-medium text-slate-500" for="share-url">Ссылка</label>
                            <div class="mt-1 flex gap-2">
                                <input id="share-url" x-ref="shareUrl" readonly :value="shareDlg && shareDlg.link ? shareDlg.link.url : ''" @focus="$el.select()" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm">
                                <button type="button" @click="copyShare()" class="shrink-0 rounded-lg bg-accent px-3 py-2 text-sm font-semibold text-white hover:bg-accent-dark" x-text="shareDlg && shareDlg.copied ? 'Скопировано' : 'Копировать'"></button>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">
                                <span x-text="shareDlg && shareDlg.link ? 'Срок: ' + shareUntil(shareDlg.link) : ''"></span>
                                <span x-show="shareDlg && shareDlg.link && shareDlg.link.has_password"> · с паролем</span>
                                <span x-show="shareDlg && shareDlg.link && shareDlg.link.expired" class="text-red-600"> · срок истёк</span>
                                · просмотров: <span x-text="shareDlg && shareDlg.link ? shareDlg.link.views : 0"></span>
                            </p>
                        </div>

                        <fieldset class="mt-4">
                            <legend class="text-xs font-medium text-slate-500">Кто может открыть</legend>
                            <label class="mt-1.5 flex cursor-pointer items-start gap-2.5 rounded-lg p-2 hover:bg-slate-50"><input type="radio" value="members" x-model="shareDlg.form.access" class="mt-1 accent-[var(--accent)]"><span class="text-sm"><span class="font-medium">Участники диалога</span><span class="block text-xs text-slate-500">Только те, кто состоит в этом диалоге и вошёл в чат</span></span></label>
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-lg p-2 hover:bg-slate-50"><input type="radio" value="app" x-model="shareDlg.form.access" class="mt-1 accent-[var(--accent)]"><span class="text-sm"><span class="font-medium">Все, кто вошёл в чат</span><span class="block text-xs text-slate-500">Любой пользователь этого чата со ссылкой</span></span></label>
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-lg p-2 hover:bg-slate-50"><input type="radio" value="anyone" x-model="shareDlg.form.access" class="mt-1 accent-[var(--accent)]"><span class="text-sm"><span class="font-medium">Все, у кого есть ссылка</span><span class="block text-xs text-slate-500">Откроется без входа, как публичная страница</span></span></label>
                        </fieldset>

                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <label class="block text-xs font-medium text-slate-500">Срок действия
                                <select x-model="shareDlg.form.expires_in" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm text-slate-800">
                                    <option value="" x-text="shareDlg.link ? 'Не менять' : 'Без срока'"></option>
                                    <option value="3600">1 час</option><option value="86400">1 день</option><option value="604800">7 дней</option><option value="2592000">30 дней</option>
                                    <option value="never" x-show="shareDlg.link">Без срока</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">Пароль
                                <input type="password" autocomplete="new-password" minlength="4" maxlength="64" x-model="shareDlg.form.password" :placeholder="shareDlg.link && shareDlg.link.has_password ? 'Не менять' : 'Без пароля'" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                            </label>
                        </div>
                        <label x-show="shareDlg.link && shareDlg.link.has_password" class="mt-2 flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" x-model="shareDlg.form.clear_password" class="accent-[var(--accent)]">Снять пароль</label>

                        <p x-show="shareDlg.error" x-text="shareDlg.error" class="mt-3 text-sm text-red-600"></p>

                        <div class="mt-5 flex flex-wrap items-center gap-2">
                            <button type="button" @click="saveShare()" :disabled="shareDlg.saving" class="rounded-xl bg-accent px-4 py-2.5 text-sm font-semibold text-white hover:bg-accent-dark disabled:opacity-50" x-text="shareDlg.link ? 'Сохранить и копировать' : 'Создать ссылку'"></button>
                            <button type="button" x-show="shareDlg.link" @click="stopShare()" class="rounded-xl px-3 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50">Отключить ссылку</button>
                            <button type="button" @click="shareDlg = null" class="ml-auto rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>

            </template>

            {{-- Новый личный диалог --}}
            <div x-show="peopleOpen" class="absolute inset-0 z-20 flex items-start justify-center bg-black/40 p-4 pt-20" @keydown.escape.window="peopleOpen = false" @click.self="peopleOpen = false">
                <div class="w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl" role="dialog" aria-label="Новый диалог">
                    <input x-model="peopleQuery" @input.debounce.300ms="searchPeople()" placeholder="Имя собеседника" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-accent">
                    <ul class="mt-2 max-h-72 overflow-y-auto">
                        <template x-for="p in people" :key="p.id">
                            <li><button type="button" @click="startDirect(p)" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-slate-50">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-semibold text-white" :style="`background:${avatarColor(p.name)}`" x-text="initials(p.name)"></span>
                                <span class="text-sm"><span x-text="p.name"></span><span x-show="p.role" class="block text-xs text-slate-400" x-text="p.role"></span></span>
                            </button></li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </template>

    <div x-show="toast" x-transition class="pointer-events-none absolute inset-x-0 bottom-20 z-30 flex justify-center px-4" role="alert">
        <p class="rounded-lg bg-slate-900/90 px-4 py-2 text-sm text-white shadow-lg" x-text="toast"></p>
    </div>
</div>
</body>
</html>
