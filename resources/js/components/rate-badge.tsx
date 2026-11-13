import { usePage } from '@inertiajs/react';
import { formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';

/**
 * Yon menyu ostidagi bugungi dollar kursi.
 */
export function RateBadge() {
    const { store } = usePage().props;

    if (!store) {
        return null;
    }

    return (
        <div className="mt-4 flex flex-col gap-0.5 glass-inner px-3.5 py-3">
            <span className="text-xs font-semibold text-muted-foreground">
                {store.rate?.is_today === false
                    ? t('rate.last')
                    : t('rate.today')}
            </span>
            <span className="text-[17px] font-bold num">
                {store.rate
                    ? t('rate.value', {
                          value: formatNumber(store.rate.usd_uzs),
                      })
                    : t('rate.missing')}
            </span>
        </div>
    );
}
