import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { DataTable, Td, Th } from '@/components/data-table';
import { Panel } from '@/components/panel';
import { PlanStateChip } from '@/components/plan-detail';
import { Segmented } from '@/components/segmented';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { exportMethod, index } from '@/routes/reports';
import type { BranchOption, PlanRow } from '@/types';

type Period = 'day' | 'week' | 'month' | 'custom';
type CashRow = { label: string; uzs: string; usd: string };

type Props = {
    period: Period;
    from: string;
    to: string;
    branchId: number | null;
    branches: BranchOption[];
    kpis: { label: string; value: string; sub: string }[];
    expected: { label: string; value: string; percent: number }[];
    cash: { branch: CashRow[]; admin: CashRow[] };
    debtors: PlanRow[];
    sellers: { name: string; sales: number; revenue: string; profit: string }[];
    products: { name: string; qty: number; revenue: string; profit: string }[];
    stock: {
        units: number;
        accessories: number;
        cost: string;
        value: string;
        aging: { fresh: number; d30: number; d60: number; d90: number };
    };
    purchases: {
        name: string;
        type_label: string;
        purchases: number;
        total: string;
    }[];
};

function CashBlock({
    title,
    rows,
    accent,
}: {
    title: string;
    rows: CashRow[];
    accent?: boolean;
}) {
    return (
        <div
            className={
                accent
                    ? 'grid grid-cols-[1fr_auto_auto] items-center gap-x-4 gap-y-2 glass-inner bg-info-bg/70 px-4 py-3.5 text-sm'
                    : 'grid grid-cols-[1fr_auto_auto] items-center gap-x-4 gap-y-2 glass-inner px-4 py-3.5 text-sm'
            }
        >
            <span className={accent ? 'font-bold text-info' : 'font-bold'}>
                {title}
            </span>
            <span className="text-right text-xs font-semibold text-muted-foreground">
                So'm
            </span>
            <span className="text-right text-xs font-semibold text-muted-foreground">
                Dollar
            </span>
            {rows.map((row) => (
                <div key={row.label} className="contents">
                    <span>{row.label}</span>
                    <span className="text-right font-semibold num">
                        {row.uzs.replace(" so'm", '')}
                    </span>
                    <span className="text-right font-semibold num">
                        {row.usd.replace('$ ', '')}
                    </span>
                </div>
            ))}
        </div>
    );
}

export default function Reports({
    period,
    from,
    to,
    branchId,
    branches,
    kpis,
    expected,
    cash,
    debtors,
    sellers,
    products,
    stock,
    purchases,
}: Props) {
    const query = { period, branch: branchId ?? undefined, from, to };

    const apply = (next: Partial<{ period: Period; branch: number | '' }>) =>
        router.get(
            index().url,
            {
                period: next.period ?? period,
                branch:
                    next.branch === undefined
                        ? (branchId ?? undefined)
                        : next.branch || undefined,
            },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('reports.title')} />

            <div className="flex flex-wrap items-center gap-2.5">
                <Segmented
                    label={t('reports.period')}
                    className="self-center"
                    options={[
                        { value: 'day', label: t('reports.period.day') },
                        { value: 'week', label: t('reports.period.week') },
                        { value: 'month', label: t('reports.period.month') },
                    ]}
                    value={period === 'custom' ? 'month' : period}
                    onChange={(next) => apply({ period: next })}
                />
                <select
                    aria-label={t('stock.scope')}
                    value={branchId ?? ''}
                    onChange={(event) =>
                        apply({
                            branch: event.target.value
                                ? Number(event.target.value)
                                : '',
                        })
                    }
                    className="min-h-11 rounded-[14px] border border-white/90 bg-white/72 px-3 text-[15px] text-ink outline-none focus-visible:ring-[3px] focus-visible:ring-ring"
                >
                    <option value="">{t('stock.scope.all')}</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </select>
                <Button variant="secondary" asChild>
                    <a href={exportMethod({ query }).url}>
                        <Download />
                        Excel
                    </a>
                </Button>
            </div>

            <div className="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-3">
                {kpis.map((kpi) => (
                    <section
                        key={kpi.label}
                        className="flex flex-col gap-1.5 glass rounded-3xl p-[18px]"
                    >
                        <span className="text-xs font-semibold text-muted-foreground">
                            {kpi.label}
                        </span>
                        <span className="text-[22px] font-extrabold num tracking-tight">
                            {kpi.value}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {kpi.sub}
                        </span>
                    </section>
                ))}
            </div>

            <div className="flex flex-wrap items-stretch gap-4">
                <Panel
                    title={t('reports.expected')}
                    description={t('reports.expected.description')}
                    className="min-w-0 flex-[1_1_420px]"
                >
                    <div
                        role="img"
                        aria-label={expected
                            .map((bar) => `${bar.label}: ${bar.value}`)
                            .join(', ')}
                        className="flex h-[190px] items-end gap-3.5"
                    >
                        {expected.map((bar, i) => (
                            <div
                                key={bar.label}
                                className="flex h-full flex-1 flex-col items-center justify-end gap-1.5"
                            >
                                <span className="text-xs font-bold num text-ink-mid">
                                    {bar.value}
                                </span>
                                <div
                                    className={
                                        i === 0
                                            ? 'w-full max-w-[52px] rounded-[14px] bg-[rgb(0_88_194/0.85)] shadow-[inset_0_1px_0_rgb(255_255_255/0.6)]'
                                            : 'w-full max-w-[52px] rounded-[14px] bg-[rgb(0_88_194/0.32)] shadow-[inset_0_1px_0_rgb(255_255_255/0.6)]'
                                    }
                                    style={{
                                        height: `${Math.max(bar.percent, 2) * 1.3}px`,
                                    }}
                                />
                                <span className="text-xs font-semibold text-muted-foreground">
                                    {bar.label}
                                </span>
                            </div>
                        ))}
                    </div>
                </Panel>

                <Panel
                    title={t('cash.title')}
                    className="min-w-0 flex-[1_1_340px]"
                >
                    <CashBlock
                        title={
                            branches.find((branch) => branch.id === branchId)
                                ?.name ?? t('reports.cash.branch')
                        }
                        rows={cash.branch}
                    />
                    <CashBlock
                        title={t('cash.scope.admin')}
                        rows={cash.admin}
                        accent
                    />
                </Panel>
            </div>

            <Panel
                title={t('reports.debtors')}
                description={t('reports.debtors.description')}
            >
                {debtors.length === 0 ? (
                    <p className="text-sm text-ink-mid">
                        {t('reports.debtors.empty')}
                    </p>
                ) : (
                    <DataTable minWidth={720}>
                        <thead>
                            <tr>
                                <Th>{t('sales.col.customer')}</Th>
                                <Th>{t('stock.col.product')}</Th>
                                <Th>{t('plan.col.next')}</Th>
                                <Th right>{t('plan.col.monthly')}</Th>
                                <Th right>{t('plan.col.remaining')}</Th>
                                <Th>{t('sales.col.status')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {debtors.map((plan) => (
                                <tr key={plan.id}>
                                    <Td className="font-bold">
                                        {plan.customer}
                                        <span className="block text-xs font-normal num text-muted-foreground">
                                            {plan.customer_phone}
                                        </span>
                                    </Td>
                                    <Td className="text-ink-mid">
                                        {plan.product} ·{' '}
                                        {t('stock.months', {
                                            count: plan.months,
                                        })}
                                    </Td>
                                    <Td className="num">{plan.next_date}</Td>
                                    <Td right className="num">
                                        {plan.next_amount_label}
                                    </Td>
                                    <Td right className="font-bold num">
                                        {plan.remaining_label}
                                    </Td>
                                    <Td>
                                        <PlanStateChip plan={plan} />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                )}
            </Panel>

            <div className="flex flex-wrap items-start gap-4">
                <Panel
                    title={t('reports.sellers')}
                    className="min-w-0 flex-[1_1_420px]"
                >
                    <DataTable minWidth={420}>
                        <thead>
                            <tr>
                                <Th>{t('sales.col.seller')}</Th>
                                <Th right>{t('reports.col.sales')}</Th>
                                <Th right>{t('reports.col.revenue')}</Th>
                                <Th right>{t('sales.show.profit')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {sellers.map((row) => (
                                <tr key={row.name}>
                                    <Td className="font-bold">{row.name}</Td>
                                    <Td right className="num">
                                        {row.sales}
                                    </Td>
                                    <Td right className="num">
                                        {row.revenue}
                                    </Td>
                                    <Td right className="font-bold num">
                                        {row.profit}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                </Panel>

                <Panel
                    title={t('reports.products')}
                    className="min-w-0 flex-[1_1_420px]"
                >
                    <DataTable minWidth={420}>
                        <thead>
                            <tr>
                                <Th>{t('stock.col.product')}</Th>
                                <Th right>{t('sales.col.qty')}</Th>
                                <Th right>{t('reports.col.revenue')}</Th>
                                <Th right>{t('sales.show.profit')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.map((row) => (
                                <tr key={row.name}>
                                    <Td className="font-bold">{row.name}</Td>
                                    <Td right className="num">
                                        {row.qty}
                                    </Td>
                                    <Td right className="num">
                                        {row.revenue}
                                    </Td>
                                    <Td right className="font-bold num">
                                        {row.profit}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                </Panel>
            </div>

            <div className="flex flex-wrap items-start gap-4">
                <Panel
                    title={t('reports.stock')}
                    description={t('reports.stock.description')}
                    className="min-w-0 flex-[1_1_420px]"
                >
                    <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 glass-inner px-4 py-3.5 text-sm">
                        <dt>{t('reports.stock.units')}</dt>
                        <dd className="text-right font-bold num">
                            {t('stock.qty', { count: stock.units })}
                        </dd>
                        <dt>{t('reports.stock.accessories')}</dt>
                        <dd className="text-right font-bold num">
                            {t('stock.qty', { count: stock.accessories })}
                        </dd>
                        <dt>{t('reports.stock.cost')}</dt>
                        <dd className="text-right font-bold num">
                            {stock.cost}
                        </dd>
                        <dt>{t('reports.stock.value')}</dt>
                        <dd className="text-right font-bold num">
                            {stock.value}
                        </dd>
                    </dl>
                    <dl className="grid grid-cols-4 gap-2 glass-inner px-4 py-3.5 text-center text-sm">
                        {(
                            [
                                ['fresh', t('reports.aging.fresh')],
                                ['d30', t('reports.aging.d30')],
                                ['d60', t('reports.aging.d60')],
                                ['d90', t('reports.aging.d90')],
                            ] as const
                        ).map(([key, label]) => (
                            <div key={key} className="flex flex-col gap-0.5">
                                <dt className="text-xs font-semibold text-muted-foreground">
                                    {label}
                                </dt>
                                <dd
                                    className={
                                        key === 'd90' && stock.aging.d90 > 0
                                            ? 'text-lg font-extrabold num text-danger'
                                            : 'text-lg font-extrabold num'
                                    }
                                >
                                    {stock.aging[key]}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Panel>

                <Panel
                    title={t('reports.purchases')}
                    className="min-w-0 flex-[1_1_420px]"
                >
                    {purchases.length === 0 ? (
                        <p className="text-sm text-ink-mid">
                            {t('reports.purchases.empty')}
                        </p>
                    ) : (
                        <DataTable minWidth={400}>
                            <thead>
                                <tr>
                                    <Th>{t('reports.col.source')}</Th>
                                    <Th right>{t('reports.col.purchases')}</Th>
                                    <Th right>{t('sales.col.total')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {purchases.map((row) => (
                                    <tr key={row.name}>
                                        <Td className="font-bold">
                                            {row.name}
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                {row.type_label}
                                            </span>
                                        </Td>
                                        <Td right className="num">
                                            {row.purchases}
                                        </Td>
                                        <Td right className="font-bold num">
                                            {row.total}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </Panel>
            </div>
        </>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: t('reports.title'), href: index() }],
    subtitle: t('reports.subtitle'),
};
