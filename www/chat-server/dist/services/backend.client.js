const base = () => (process.env.BACKEND_URL ?? 'http://localhost/api/v1').replace(/\/$/, '');
export async function pingBackend() {
    const url = `${base()}/health`;
    const t0 = Date.now();
    try {
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: AbortSignal.timeout(8000),
        });
        return {
            ok: res.ok,
            latencyMs: Date.now() - t0,
            error: res.ok ? undefined : `HTTP ${res.status}`,
        };
    }
    catch (e) {
        return {
            ok: false,
            latencyMs: Date.now() - t0,
            error: e instanceof Error ? e.message : 'fetch failed',
        };
    }
}
export async function assertRoomAccess(roomId, token) {
    const res = await fetch(`${base()}/chats/${roomId}`, {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
    });
    return res.ok;
}
export async function sendMessageViaRest(roomId, token, body) {
    const res = await fetch(`${base()}/chats/${roomId}/messages`, {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body: JSON.stringify(body),
    });
    if (!res.ok) {
        return null;
    }
    const json = (await res.json());
    return json.success && json.data ? json.data : null;
}
export async function markReadViaRest(roomId, token, messageIds, readerType) {
    const res = await fetch(`${base()}/chats/${roomId}/read`, {
        method: 'PUT',
        headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ messageIds, readerType }),
    });
    if (!res.ok) {
        return 0;
    }
    const json = (await res.json());
    return json.data?.updatedCount ?? 0;
}
