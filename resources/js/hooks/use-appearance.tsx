export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
};

/**
 * Liquid Glass dizayni faqat yorug' rejimda chizilgan, shuning uchun
 * ilova tizim sozlamasidan qat'i nazar yorug' ko'rinishda ishlaydi.
 */
export function useAppearance(): UseAppearanceReturn {
    return { appearance: 'light', resolvedAppearance: 'light' };
}
