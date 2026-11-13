import { LayoutGrid, Settings } from 'lucide-react';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

/**
 * Yon menyu. Bo'limlar tayyor bo'lgani sari shu yerga qo'shiladi.
 */
export function mainNavigation(): NavItem[] {
    return [
        {
            title: t('nav.home'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('nav.settings'),
            href: editProfile(),
            icon: Settings,
            matchPrefix: '/settings',
        },
    ];
}
