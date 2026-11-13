import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { t } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

function settingsNavigation(): NavItem[] {
    return [
        { title: t('profile.title'), href: edit() },
        { title: t('security.title'), href: editSecurity() },
    ];
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="flex flex-col gap-4">
            <nav
                aria-label={t('nav.settings')}
                className="flex flex-wrap gap-1 self-start rounded-full bg-muted p-1"
            >
                {settingsNavigation().map((item) => {
                    const active = isCurrentOrParentUrl(item.href);

                    return (
                        <Link
                            key={toUrl(item.href)}
                            href={item.href}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'flex min-h-[38px] items-center rounded-full px-4 text-sm',
                                active
                                    ? 'bg-white font-bold text-ink shadow-[0_2px_8px_rgb(28_35_64/0.14),inset_0_1px_0_#fff]'
                                    : 'font-semibold text-ink-mid',
                            )}
                        >
                            {item.title}
                        </Link>
                    );
                })}
            </nav>

            <section className="max-w-2xl space-y-10 glass p-5 sm:p-6">
                {children}
            </section>
        </div>
    );
}
