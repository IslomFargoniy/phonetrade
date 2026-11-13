/**
 * Sahifani almashtirmaydigan so'rovlar uchun (skaner qidiruvi, savat hisobi).
 * Sahifa o'zgaradigan amallar Inertia orqali yuboriladi.
 */

function xsrfToken(): string {
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);

    return match ? decodeURIComponent(match[1]) : '';
}

export type JsonResult<T> =
    | { ok: true; data: T }
    | { ok: false; status: number; message: string };

async function parse<T>(response: Response): Promise<JsonResult<T>> {
    const body: unknown = await response.json().catch(() => null);

    if (response.ok) {
        return { ok: true, data: body as T };
    }

    const message =
        body && typeof body === 'object' && 'message' in body
            ? String((body as { message: unknown }).message)
            : '';

    return { ok: false, status: response.status, message };
}

export async function getJson<T>(url: string): Promise<JsonResult<T>> {
    return parse<T>(
        await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }),
    );
}

export async function postJson<T>(
    url: string,
    body: unknown,
): Promise<JsonResult<T>> {
    return parse<T>(
        await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }),
    );
}
