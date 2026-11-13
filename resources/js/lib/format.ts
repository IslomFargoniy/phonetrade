/**
 * Sonni minglik bo'shliqlari bilan chiqaradi: `12750.00` → `12 750`, `12.5` → `12.5`.
 */
export function formatNumber(value: string | number): string {
    const [whole, fraction = ''] = String(value).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const trimmed = fraction.replace(/0+$/, '');

    return trimmed ? `${grouped}.${trimmed}` : grouped;
}
