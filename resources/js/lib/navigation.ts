import {
    ArrowLeftRight,
    BarChart3,
    History,
    Wallet,
    CalendarClock,
    Users,
    Building2,
    PackagePlus,
    Receipt,
    ScanLine,
    Settings,
    Warehouse,
} from 'lucide-react';
import { t } from '@/lib/i18n';
import { index as tenants } from '@/routes/admin/tenants';
import { index as cash } from '@/routes/cash';
import { index as customers } from '@/routes/customers';
import { index as installments } from '@/routes/installments';
import { index as pos } from '@/routes/pos';
import { edit as editProfile } from '@/routes/profile';
import { create as receipt } from '@/routes/receipts';
import { index as reports } from '@/routes/reports';
import { index as sales } from '@/routes/sales';
import { history, index as stock } from '@/routes/stock';
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

    const owner: NavItem[] =
        role === 'owner'
            ? [
                  {
                      title: t('reports.title'),
                      href: reports(),
                      icon: BarChart3,
                      matchPrefix: '/reports',
                  },
              ]
            : [];

    return [
        ...owner,
        {
            title: t('pos.title'),
            href: pos(),
            icon: ScanLine,
            matchPrefix: '/pos',
        },
        {
            title: t('sales.title'),
            href: sales(),
            icon: Receipt,
            matchPrefix: '/sales',
        },
        {
            title: t('plan.title'),
            href: installments(),
            icon: CalendarClock,
            matchPrefix: '/installments',
        },
        {
            title: t('customers.title'),
            href: customers(),
            icon: Users,
            matchPrefix: '/customers',
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
            title: t('cash.title'),
            href: cash(),
            icon: Wallet,
            matchPrefix: '/cash',
        },
        {
            title: t('history.title'),
            href: history(),
            icon: History,
            matchPrefix: '/stock/history',
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
