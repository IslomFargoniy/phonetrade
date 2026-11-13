import { Building2, LayoutGrid, Settings } from 'lucide-react';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { index as tenants } from '@/routes/admin/tenants';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem, Role } from '@/types';

/**
 * Yon menyu. Bo'limlar tayyor bo'lgani sari shu yerga qo'shiladi.
 */
export function mainNavigation(role: Role): NavItem[] {
    const settings: NavItem = {
        title: t('nav.settings'),
        href: editProfile(),
        icon: Settings,
        matchPrefix: '/settings',
    };

    if (role === 'superadmin') {
        return [
            {
                title: t('nav.tenants'),
                href: tenants(),
                icon: Building2,
                matchPrefix: '/admin/tenants',
            },
            settings,
        ];
    }

    return [
        {
            title: t('nav.home'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        settings,
    ];
}
