import { Link, usePage } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { mainNavigation } from '@/lib/navigation';
import { cn, toUrl } from '@/lib/utils';

export function AppNav({ onNavigate }: { onNavigate?: () => void }) {
    const { auth } = usePage().props;
    const { currentUrl, isCurrentUrl } = useCurrentUrl();

    const items = mainNavigation(auth.user.role);
    const bestPrefix = items
        .map((item) => item.matchPrefix)
        .filter(
            (prefix): prefix is string =>
                prefix !== undefined && currentUrl.startsWith(prefix),
        )
        .sort((a, b) => b.length - a.length)[0];

    return (
        <nav className="flex flex-col gap-1">
            {items.map((item) => {
                // Eng uzun mos prefiks yutadi: /stock/history faqat "IMEI tarixi"ni yoqadi, "Ombor"ni emas.
                const active = item.matchPrefix
                    ? item.matchPrefix === bestPrefix
                    : isCurrentUrl(item.href);

                return (
                    <Link
                        key={toUrl(item.href)}
                        href={item.href}
                        prefetch
                        onClick={onNavigate}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'flex min-h-11 items-center gap-3 rounded-[14px] px-3.5 text-sm transition-colors',
                            active
                                ? 'bg-white/92 font-bold text-primary shadow-[0_2px_10px_rgb(28_35_64/0.1),inset_0_1px_0_#fff]'
                                : 'font-medium text-ink-soft hover:bg-white/50',
                        )}
                    >
                        {item.icon && (
                            <item.icon className="size-5" strokeWidth={1.8} />
                        )}
                        <span>{item.title}</span>
                    </Link>
                );
            })}
        </nav>
    );
}
