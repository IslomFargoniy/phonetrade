import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Chip } from '@/components/chip';
import type { ChipTone } from '@/components/chip';
import { DataTable, Td, Th } from '@/components/data-table';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import { index, show } from '@/routes/sales';

export type SaleSummary = {
    id: number;
    number: number;
    date: string | null;
    type: 'full' | 'installment';
    type_label: string;
    status: 'completed' | 'partially_returned' | 'returned';
    status_label: string;
    total_label: string;
    seller: string | null;
    customer: string | null;
    items: string;
};

export const saleStatusTone: Record<SaleSummary['status'], ChipTone> = {
    completed: 'success',
    partially_returned: 'warning',
    returned: 'danger',
};

export default function Sales({
    q,
    sales,
}: {
    q: string;
    sales: SaleSummary[];
}) {
    const [search, setSearch] = useState(q);

    return (
        <>
            <Head title={t('sales.title')} />

            <Panel className="py-3.5">
                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            index().url,
                            { q: search },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <Input
                        aria-label={t('sales.search')}
                        placeholder={t('sales.search.placeholder')}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="min-w-0 flex-1 basis-64"
                    />
                    <Button type="submit" variant="secondary">
                        {t('sales.search.submit')}
                    </Button>
                </form>
            </Panel>

            <Panel>
                {sales.length === 0 ? (
                    <p className="text-sm text-ink-mid">{t('sales.empty')}</p>
                ) : (
                    <DataTable minWidth={760}>
                        <thead>
                            <tr>
                                <Th>№</Th>
                                <Th>{t('sales.col.date')}</Th>
                                <Th>{t('sales.col.items')}</Th>
                                <Th>{t('sales.col.customer')}</Th>
                                <Th>{t('sales.col.seller')}</Th>
                                <Th right>{t('sales.col.total')}</Th>
                                <Th>{t('sales.col.status')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {sales.map((sale) => (
                                <tr key={sale.id}>
                                    <Td className="font-bold num">
                                        <Link
                                            href={show(sale.id)}
                                            className="text-primary underline-offset-4 hover:underline"
                                        >
                                            {sale.number}
                                        </Link>
                                    </Td>
                                    <Td className="text-[13px] num whitespace-nowrap">
                                        {sale.date}
                                    </Td>
                                    <Td>{sale.items}</Td>
                                    <Td>{sale.customer}</Td>
                                    <Td>{sale.seller}</Td>
                                    <Td
                                        right
                                        className="font-bold num whitespace-nowrap"
                                    >
                                        {sale.total_label}
                                    </Td>
                                    <Td>
                                        <Chip
                                            tone={saleStatusTone[sale.status]}
                                        >
                                            {sale.status_label}
                                        </Chip>
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                )}
            </Panel>
        </>
    );
}

Sales.layout = {
    breadcrumbs: [{ title: t('sales.title'), href: index() }],
    subtitle: t('sales.subtitle'),
};
