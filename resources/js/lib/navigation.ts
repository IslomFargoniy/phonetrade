import {
    ArrowLeftRight,
    Building2,
    LayoutGrid,
    PackagePlus,
    Settings,
    Warehouse,
} from 'lucide-react';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { index as tenants } from '@/routes/admin/tenants';
import { edit as editProfile } from '@/routes/profile';
import { create as receipt } from '@/routes/receipts';
import { index as stock } from '@/routes/stock';
import { index as transfers } from '@/routes/transfers';
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
        {
            title: t('receipt.title'),
            href: receipt(),
            icon: PackagePlus,
            matchPrefix: '/receipts',
        },
        {
            title: t('stock.title'),
            href: stock(),
            icon: Warehouse,
            matchPrefix: '/stock',
        },
        {
            title: t('transfers.title'),
            href: transfers(),
            icon: ArrowLeftRight,
            matchPrefix: '/transfers',
        },
        settings,
    ];
}
