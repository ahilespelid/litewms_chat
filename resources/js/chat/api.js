export class ApiError extends Error {
    constructor(status, body) {
        super(body?.message || `HTTP ${status}`);
        this.status = status;
        this.body = body;
    }

    /** Первая ошибка валидации или общее сообщение. */
    get text() {
        const first = this.body?.errors && Object.values(this.body.errors)[0]?.[0];
        return first || this.message;
    }
}

export function createApi({ base = '/api/v1', getToken, getSocketId, onUnauthorized }) {
    async function request(method, path, { json, form, query } = {}, retry = true) {
        const headers = { Accept: 'application/json' };
        const token = getToken?.();
        if (token) headers.Authorization = `Bearer ${token}`;

        const socketId = getSocketId?.();
        if (socketId) headers['X-Socket-ID'] = socketId;

        let body;
        if (json !== undefined) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(json);
        } else if (form) {
            body = form;
        }

        const qs = query ? '?' + new URLSearchParams(Object.entries(query).filter(([, v]) => v !== undefined && v !== null && v !== '')).toString() : '';
        const res = await fetch(base + path + qs, { method, headers, body });

        if (res.status === 401 && retry && token && onUnauthorized && (await onUnauthorized())) {
            return request(method, path, { json, form, query }, false);
        }

        const data = await res.json().catch(() => null);
        if (!res.ok) throw new ApiError(res.status, data);

        return data;
    }

    return {
        get: (path, query) => request('GET', path, { query }),
        post: (path, json) => request('POST', path, { json: json ?? {} }),
        patch: (path, json) => request('PATCH', path, { json }),
        del: (path) => request('DELETE', path, {}),
        upload: (path, form) => request('POST', path, { form }),
    };
}
