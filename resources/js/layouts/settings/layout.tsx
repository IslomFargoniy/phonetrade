import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { t } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';
import { index as branches } from '@/routes/branches';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as staff } from '@/routes/staff';
import { edit as editStore } from '@/routes/store-settings';
import type { NavItem, Role } from '@/types';

function settingsNavigation(role: Role): NavItem[] {
    const personal: NavItem[] = [
        { title: t('profile.title'), href: edit() },
        { title: t('security.title'), href: editSecurity() },
    ];

    if (role !== 'owner') {
        return personal;
    }

    // Do'kon sozlamalari faqat egasiga ko'rinadi.
    return [
        { title: t('store.tab'), href: editStore() },
        { title: t('branches.title'), href: branches() },
        { title: t('staff.title'), href: staff() },
        ...personal,
    ];
}

/** Bir nechta paneldan iborat keng sahifalar o'z joylashuvini o'zi belgilaydi */
const wideSections = [
    '/settings/store',
    '/settings/branches',
    '/settings/staff',
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { auth } = usePage().props;
    const { currentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const wide = wideSections.some((prefix) => currentUrl.startsWith(prefix));

    return (
        <div className="flex flex-col gap-4">
            <nav
                aria-label={t('nav.settings')}
                className="flex flex-wrap gap-1 self-start rounded-[22px] bg-muted p-1"
            >
                {settingsNavigation(auth.user.role).map((item) => {
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

            {wide ? (
                children
            ) : (
                <section className="max-w-2xl space-y-10 glass p-5 sm:p-6">
                    {children}
                </section>
            )}
        </div>
    );
}
