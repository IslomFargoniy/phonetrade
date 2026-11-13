/** `998901234567` → `+998 90 123 45 67` */
export function formatPhone(phone: string): string {
    const match = /^(998)(\d{2})(\d{3})(\d{2})(\d{2})$/.exec(phone);

    return match
        ? `+${match[1]} ${match[2]} ${match[3]} ${match[4]} ${match[5]}`
        : phone;
}
