import { Form, Head, Link, router } from '@inertiajs/react';
import { Phone, Printer } from 'lucide-react';
import { useState } from 'react';
import { DataTable, Td, Th } from '@/components/data-table';
import { Field } from '@/components/field';
import { Panel } from '@/components/panel';
import { PlanDetail, PlanStateChip } from '@/components/plan-detail';
import { ProductImage } from '@/components/product-image';
import { Segmented } from '@/components/segmented';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, pay } from '@/routes/installments';
import { show as showSale } from '@/routes/sales';
import type { PlanRow } from '@/types';

type Filter = 'today' | 'late' | 'active' | 'closed';
type Currency = 'UZS' | 'USD';
type Method = 'cash' | 'card' | 'transfer';

type Props = {
    filter: Filter;
    q: string;
    counts: { today: number; late: number; active: number };
    plans: PlanRow[];
    rate: string | null;
    storeName: string;
};

/** Qolgan summani tanlangan to'lov valyutasida taklif qiladi (dollarli qarz so'mda to'lansa — bugungi kurs bilan). */
function suggested(plan: PlanRow, currency: Currency, rate: string | null) {
    const amount = Number(plan.next_amount ?? plan.remaining);

    if (currency === plan.currency) {
        return String(amount);
    }

    if (!rate) {
        return '';
    }

    return currency === 'UZS'
        ? String(Math.round(amount * Number(rate)))
        : String(Math.round((amount / Number(rate)) * 100) / 100);
}

function PayForm({ plan, rate }: { plan: PlanRow; rate: string | null }) {
    const [method, setMethod] = useState<Method>('cash');
    const [currency, setCurrency] = useState<Currency>(plan.currency);
    const [amount, setAmount] = useState(suggested(plan, plan.currency, rate));

    return (
        <Form
            {...pay.form(plan.id)}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-3"
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="method" value={method} />
                    <input type="hidden" name="currency" value={currency} />
                    <Field
                        label={t('plan.pay.amount', {
                            currency: currency === 'USD' ? '$' : "so'm",
                        })}
                        name="amount"
                        inputMode="decimal"
                        required
                        className="text-right text-lg font-bold num"
                        value={amount}
                        onChange={(event) =>
                            setAmount(event.target.value.replace(/[^\d.]/g, ''))
                        }
                        error={errors.amount}
                    />
                    <Segmented
                        label={t('plan.pay.method')}
                        className="self-stretch [&>button]:flex-1"
                        options={[
                            { value: 'cash', label: 'Naqd' },
                            { value: 'card', label: 'Karta' },
                            { value: 'transfer', label: "O'tkazma" },
                        ]}
                        value={method}
                        onChange={setMethod}
                    />
                    <Segmented
                        label={t('stock.currency')}
                        options={[
                            { value: 'UZS', label: "So'm" },
                            { value: 'USD', label: 'Dollar' },
                        ]}
                        value={currency}
                        onChange={(next) => {
                            setCurrency(next);
                            setAmount(suggested(plan, next, rate));
                        }}
                    />
                    <p className="text-xs leading-relaxed text-muted-foreground">
                        {currency !== plan.currency && rate
                            ? t('plan.pay.converted', {
                                  rate: formatNumber(rate),
                              })
                            : t('plan.pay.hint')}
                    </p>
                    <Button type="submit" size="lg" disabled={processing}>
                        {processing && <Spinner />}
                        {t('plan.pay.submit')}
                    </Button>
                </>
            )}
        </Form>
    );
}

export default function Installments({
    filter,
    q,
    counts,
    plans,
    rate,
    storeName,
}: Props) {
    const [search, setSearch] = useState(q);
    const [selectedId, setSelectedId] = useState<number | null>(
        plans[0]?.id ?? null,
    );
    const selected =
        plans.find((plan) => plan.id === selectedId) ?? plans[0] ?? null;

    const apply = (next: { filter?: Filter; q?: string }) =>
        router.get(
            index().url,
            { filter, q: search, ...next },
            { preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title={t('plan.title')} />

            <div className="flex flex-wrap items-start gap-4 print:hidden">
                <Panel className="min-w-0 flex-[999_1_520px]">
                    <div className="flex flex-wrap items-center justify-between gap-2.5">
                        <Segmented
                            label={t('plan.filter')}
                            options={[
                                {
                                    value: 'today',
                                    label: t('plan.filter.today', {
                                        count: counts.today,
                                    }),
                                },
                                {
                                    value: 'late',
                                    label: t('plan.filter.late', {
                                        count: counts.late,
                                    }),
                                },
                                {
                                    value: 'active',
                                    label: t('plan.filter.active', {
                                        count: counts.active,
                                    }),
                                },
                                {
                                    value: 'closed',
                                    label: t('plan.filter.closed'),
                                },
                            ]}
                            value={filter}
                            onChange={(next) => apply({ filter: next })}
                        />
                        <form
                            className="min-w-0 flex-1 basis-56"
                            onSubmit={(event) => {
                                event.preventDefault();
                                apply({});
                            }}
                        >
                            <Input
                                aria-label={t('plan.search')}
                                placeholder={t('plan.search.placeholder')}
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </form>
                    </div>

                    {plans.length === 0 ? (
                        <p className="text-sm text-ink-mid">
                            {t('plan.empty')}
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
                                {plans.map((plan) => (
                                    <tr
                                        key={plan.id}
                                        onClick={() => setSelectedId(plan.id)}
                                        className={cn(
                                            'cursor-pointer',
                                            plan.id === selected?.id &&
                                                'bg-info-bg/60',
                                        )}
                                    >
                                        <Td>
                                            <button
                                                type="button"
                                                className="cursor-pointer text-left font-bold"
                                                onClick={() =>
                                                    setSelectedId(plan.id)
                                                }
                                            >
                                                {plan.customer}
                                            </button>
                                        </Td>
                                        <Td className="text-ink-mid">
                                            <ProductImage
                                                src={plan.image_url}
                                                name={plan.product}
                                                className="float-left mr-2.5 size-9 rounded-[10px]"
                                            />
                                            {plan.product} ·{' '}
                                            {t('stock.months', {
                                                count: plan.months,
                                            })}
                                        </Td>
                                        <Td className="num">
                                            {plan.next_date}
                                        </Td>
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

                {selected && (
                    <aside className="flex min-w-0 flex-[1_1_360px] flex-col gap-4">
                        <Panel
                            title={selected.customer}
                            description={`${selected.product} · ${t('stock.months', { count: selected.months })} · +${selected.markup_pct}%`}
                            action={<PlanStateChip plan={selected} />}
                        >
                            <div className="flex flex-wrap gap-2">
                                <Button variant="secondary" size="sm" asChild>
                                    <a
                                        href={`tel:+${selected.customer_phone_raw}`}
                                    >
                                        <Phone />
                                        {selected.customer_phone}
                                    </a>
                                </Button>
                                <Button variant="secondary" size="sm" asChild>
                                    <Link href={showSale(selected.sale_id)}>
                                        {t('plan.sale', {
                                            number: selected.sale_number,
                                        })}
                                    </Link>
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => window.print()}
                                >
                                    <Printer />
                                    {t('plan.print')}
                                </Button>
                            </div>
                            <PlanDetail plan={selected} />
                        </Panel>

                        {selected.status === 'active' && (
                            <Panel title={t('plan.pay.title')}>
                                <PayForm
                                    key={`${selected.id}-${selected.remaining}`}
                                    plan={selected}
                                    rate={rate}
                                />
                            </Panel>
                        )}
                    </aside>
                )}
            </div>

            {selected && (
                <div className="hidden bg-white p-6 text-[11pt] text-black print:block">
                    <h1 className="text-[16pt] font-bold">
                        {t('plan.contract.title', { id: selected.id })}
                    </h1>
                    <p>{storeName}</p>
                    <p className="mt-3">
                        {t('plan.contract.customer')}: {selected.customer},{' '}
                        {selected.customer_phone}
                    </p>
                    <p>
                        {t('plan.contract.product')}: {selected.product}
                    </p>
                    <p>
                        {t('plan.contract.terms', {
                            months: selected.months,
                            pct: selected.markup_pct,
                            total: selected.total_label,
                        })}
                    </p>
                    {selected.down_payment_label && (
                        <p>
                            {t('pos.down_payment')}:{' '}
                            {selected.down_payment_label}
                        </p>
                    )}
                    <table className="mt-4 w-full border-collapse">
                        <thead>
                            <tr>
                                <th className="border border-black p-1 text-left">
                                    №
                                </th>
                                <th className="border border-black p-1 text-left">
                                    {t('sales.col.date')}
                                </th>
                                <th className="border border-black p-1 text-right">
                                    {t('pos.amount')}
                                </th>
                                <th className="border border-black p-1 text-right">
                                    {t('plan.paid')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {selected.schedule.map((row) => (
                                <tr key={row.no}>
                                    <td className="border border-black p-1">
                                        {row.no}
                                    </td>
                                    <td className="border border-black p-1">
                                        {row.date}
                                    </td>
                                    <td className="border border-black p-1 text-right">
                                        {row.amount_label}
                                    </td>
                                    <td className="border border-black p-1 text-right">
                                        {row.paid_label}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="mt-3">{t('pos.no_penalty')}</p>
                    <div className="mt-12 flex justify-between">
                        <span>{t('plan.contract.seller_sign')}</span>
                        <span>{t('plan.contract.customer_sign')}</span>
                    </div>
                </div>
            )}
        </>
    );
}

Installments.layout = {
    breadcrumbs: [{ title: t('plan.title'), href: index() }],
    subtitle: t('plan.subtitle'),
};
