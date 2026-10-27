export function formatDuration(totalSeconds) {
    const seconds = Math.max(0, Math.round(totalSeconds || 0));
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

export function formatFileSize(bytes) {
    if (!bytes) return '';
    const units = ['Б', 'КБ', 'МБ', 'ГБ'];
    let size = bytes;
    let i = 0;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    return `${size.toFixed(i === 0 || size >= 10 ? 0 : 1)} ${units[i]}`;
}

const escapes = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => escapes[c]);

/** Текст сообщения → безопасный HTML: сначала экранируем всё, потом оборачиваем только http(s)-ссылки. */
export function linkify(text) {
    return escapeHtml(text).replace(
        /\bhttps?:\/\/[^\s<]+[^\s<.,:;"')\]!?]/g,
        (url) => `<a href="${url}" target="_blank" rel="noopener noreferrer nofollow">${url}</a>`
    );
}

const time = new Intl.DateTimeFormat('ru-RU', { hour: '2-digit', minute: '2-digit' });
const dayFull = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
const dayShort = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short' });

export const formatTime = (iso) => (iso ? time.format(new Date(iso)) : '');

export function dayLabel(iso) {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date(Date.now() - 86400000);
    if (d.toDateString() === today.toDateString()) return 'Сегодня';
    if (d.toDateString() === yesterday.toDateString()) return 'Вчера';
    return d.getFullYear() === today.getFullYear() ? dayShort.format(d) : dayFull.format(d);
}

/** «14:05» сегодня, «Вчера», «5 окт» — для списка диалогов. */
export function listTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toDateString() === new Date().toDateString() ? time.format(d) : dayLabel(iso);
}

export function initials(name) {
    const parts = String(name || '?').trim().split(/\s+/);
    return ((parts[0]?.[0] || '?') + (parts[1]?.[0] || '')).toUpperCase();
}

/** Стабильный цвет аватара по имени. */
export function avatarColor(seed) {
    let h = 0;
    for (const c of String(seed)) h = (h * 31 + c.charCodeAt(0)) % 360;
    return `hsl(${h} 45% 42%)`;
}
