import { usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Muddat tugashiga shuncha kun qolganda egasi ogohlantiriladi */
const WARN_DAYS = 5;

/**
 * Obuna ogohlantirishi: muddat yaqinlashganda (faqat egasiga), imtiyozli davrda
 * va faqat o'qish rejimida (hamma xodimga) ko'rinadi.
 */
export function SubscriptionBanner() {
    const { store, auth } = usePage().props;
    const subscription = store?.subscription;

    if (!subscription) {
        return null;
    }

    const isOwner = auth.user?.role === 'owner';
    const { state, days_left: daysLeft } = subscription;

    let message: string | null = null;

    if (state === 'expired') {
        message = t(
            isOwner ? 'subscription.expired.owner' : 'subscription.expired',
        );
    } else if (state === 'grace' && isOwner) {
        message = t('subscription.grace', {
            date: subscription.read_only_from ?? '',
        });
    } else if (
        state === 'active' &&
        isOwner &&
        daysLeft !== null &&
        daysLeft <= WARN_DAYS
    ) {
        message =
            daysLeft === 0
                ? t('subscription.ends_today')
                : t('subscription.ends_soon', { days: daysLeft });
    }

    if (message === null) {
        return null;
    }

    return (
        <div
            role="alert"
            className={cn(
                'flex items-start gap-3 rounded-[22px] border px-4 py-3 text-sm font-semibold print:hidden',
                state === 'expired'
                    ? 'border-red-200 bg-red-50/90 text-red-800'
                    : 'border-amber-200 bg-amber-50/90 text-amber-900',
            )}
        >
            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
            <span>{message}</span>
        </div>
    );
}
