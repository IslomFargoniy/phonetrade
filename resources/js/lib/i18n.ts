import { uz } from '@/lang/uz';

export type TranslationKey = keyof typeof uz;

/**
 * Interfeys matnlari bitta lug'atdan olinadi. Boshqa til qo'shilganda
 * shu yerda lug'at tanlanadi, sahifalar o'zgarmaydi.
 */
export function t(
    key: TranslationKey,
    params?: Record<string, string | number>,
): string {
    const template: string = uz[key];

    if (!params) {
        return template;
    }

    return template.replace(/:(\w+)/g, (match, name: string) =>
        name in params ? String(params[name]) : match,
    );
}
