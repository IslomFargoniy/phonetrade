import { Form, Head, Link, router } from '@inertiajs/react';
import { FileImage } from 'lucide-react';
import { useState } from 'react';
import { Chip } from '@/components/chip';
import { DataTable, Td, Th } from '@/components/data-table';
import { Field } from '@/components/field';
import InputError from '@/components/input-error';
import { Panel } from '@/components/panel';
import { PlanDetail, PlanStateChip } from '@/components/plan-detail';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { saleStatusTone } from '@/pages/sales/index';
import type { SaleSummary } from '@/pages/sales/index';
import { index, update } from '@/routes/customers';
import { show as showSale } from '@/routes/sales';
import type { PlanRow } from '@/types';

type CustomerRow = {
    id: number;
    full_name: string;
    phone: string;
    active_plans: number;
};

type Selected = {
    id: number;
    full_name: string;
    phone: string;
    phone2: string | null;
    passport_series: string | null;
    passport_number: string | null;
    pinfl: string | null;
    address: string | null;
    files: { id: number; name: string; url: string }[];
    sales: SaleSummary[];
    plans: PlanRow[];
};

type Props = {
    q: string;
    customers: CustomerRow[];
    selected: Selected | null;
};

export default function Customers({ q, customers, selected }: Props) {
    const [search, setSearch] = useState(q);

    const open = (id: number | null, query = search) =>
        router.get(
            index().url,
            { q: query, ...(id ? { customer: id } : {}) },
            { preserveScroll: true, preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={t('customers.title')} />

            <div className="flex flex-wrap items-start gap-4">
                <Panel className="min-w-0 flex-[1_1_320px]">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            open(selected?.id ?? null);
                        }}
                    >
                        <Input
                            aria-label={t('customers.search')}
                            placeholder={t('customers.search.placeholder')}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </form>

                    {customers.length === 0 ? (
                        <p className="text-sm text-ink-mid">
                            {t('customers.empty')}
                        </p>
                    ) : (
                        <div className="flex flex-col gap-1">
                            {customers.map((customer) => (
                                <button
                                    key={customer.id}
                                    type="button"
                                    aria-current={
                                        customer.id === selected?.id
                                            ? 'true'
                                            : undefined
                                    }
                                    onClick={() => open(customer.id)}
                                    className={cn(
                                        'flex min-h-14 cursor-pointer items-center justify-between gap-2.5 rounded-[18px] px-3.5 py-2 text-left',
                                        customer.id === selected?.id
                                            ? 'bg-white/92 shadow-[0_2px_10px_rgb(28_35_64/0.1)]'
                                            : 'hover:bg-white/50',
                                    )}
                                >
                                    <span className="flex min-w-0 flex-col">
                                        <span className="truncate text-[15px] font-bold">
                                            {customer.full_name}
                                        </span>
                                        <span className="text-xs num text-muted-foreground">
                                            {customer.phone}
                                        </span>
                                    </span>
                                    {customer.active_plans > 0 && (
                                        <Chip tone="info">
                                            {t('customers.active_plans', {
                                                count: customer.active_plans,
                                            })}
                                        </Chip>
                                    )}
                                </button>
                            ))}
                        </div>
                    )}
                </Panel>

                <div className="flex min-w-0 flex-[999_1_520px] flex-col gap-4">
                    {!selected ? (
                        <Panel>
                            <p className="text-sm text-ink-mid">
                                {t('customers.pick')}
                            </p>
                        </Panel>
                    ) : (
                        <>
                            <Panel title={t('customers.card')}>
                                <Form
                                    key={selected.id}
                                    {...update.form(selected.id)}
                                    options={{ preserveScroll: true }}
                                    className="flex flex-col gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <Field
                                                label={t(
                                                    'pos.customer.fullname',
                                                )}
                                                name="full_name"
                                                required
                                                defaultValue={
                                                    selected.full_name
                                                }
                                                error={errors.full_name}
                                            />
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <Field
                                                    label={t('field.phone')}
                                                    name="phone"
                                                    type="tel"
                                                    required
                                                    defaultValue={
                                                        selected.phone
                                                    }
                                                    error={errors.phone}
                                                />
                                                <Field
                                                    label={t('customer.phone2')}
                                                    name="phone2"
                                                    type="tel"
                                                    defaultValue={
                                                        selected.phone2 ?? ''
                                                    }
                                                    error={errors.phone2}
                                                />
                                                <div className="grid grid-cols-[72px_1fr] gap-2">
                                                    <Field
                                                        label={t(
                                                            'customers.series',
                                                        )}
                                                        name="passport_series"
                                                        maxLength={2}
                                                        className="uppercase"
                                                        defaultValue={
                                                            selected.passport_series ??
                                                            ''
                                                        }
                                                        error={
                                                            errors.passport_series
                                                        }
                                                    />
                                                    <Field
                                                        label={t(
                                                            'customer.passport_number',
                                                        )}
                                                        name="passport_number"
                                                        maxLength={7}
                                                        className="num"
                                                        defaultValue={
                                                            selected.passport_number ??
                                                            ''
                                                        }
                                                        error={
                                                            errors.passport_number
                                                        }
                                                    />
                                                </div>
                                                <Field
                                                    label={t('customer.pinfl')}
                                                    name="pinfl"
                                                    maxLength={14}
                                                    className="num"
                                                    defaultValue={
                                                        selected.pinfl ?? ''
                                                    }
                                                    error={errors.pinfl}
                                                />
                                            </div>
                                            <Field
                                                label={t('field.address')}
                                                name="address"
                                                defaultValue={
                                                    selected.address ?? ''
                                                }
                                                error={errors.address}
                                            />

                                            <div className="flex flex-col gap-1.5">
                                                <span className="text-xs font-semibold text-muted-foreground">
                                                    {t(
                                                        'customer.passport_photo',
                                                    )}
                                                </span>
                                                {selected.files.length > 0 && (
                                                    <div className="flex flex-wrap gap-2">
                                                        {selected.files.map(
                                                            (file) => (
                                                                <Button
                                                                    key={
                                                                        file.id
                                                                    }
                                                                    variant="secondary"
                                                                    size="sm"
                                                                    asChild
                                                                >
                                                                    <a
                                                                        href={
                                                                            file.url
                                                                        }
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                    >
                                                                        <FileImage />
                                                                        <span className="max-w-40 truncate">
                                                                            {
                                                                                file.name
                                                                            }
                                                                        </span>
                                                                    </a>
                                                                </Button>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                                <input
                                                    type="file"
                                                    name="passport_files[]"
                                                    aria-label={t(
                                                        'customers.upload',
                                                    )}
                                                    multiple
                                                    accept="image/*,application/pdf"
                                                    className="min-h-11 w-full rounded-[14px] border border-dashed border-ink-mid/45 bg-white/40 p-2 text-sm file:mr-3 file:min-h-9 file:cursor-pointer file:rounded-full file:border-0 file:bg-white file:px-4 file:text-sm file:font-semibold"
                                                />
                                                <InputError
                                                    message={
                                                        errors.passport_files
                                                    }
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                className="self-start"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {t('action.save')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </Panel>

                            {selected.plans.map((plan) => (
                                <Panel
                                    key={plan.id}
                                    title={t('customers.plan', {
                                        product: plan.product,
                                    })}
                                    description={`${plan.created_at ?? ''} · ${t('stock.months', { count: plan.months })} · +${plan.markup_pct}%`}
                                    action={<PlanStateChip plan={plan} />}
                                >
                                    <PlanDetail plan={plan} />
                                </Panel>
                            ))}

                            {selected.sales.length > 0 && (
                                <Panel title={t('customers.sales')}>
                                    <DataTable minWidth={520}>
                                        <thead>
                                            <tr>
                                                <Th>№</Th>
                                                <Th>{t('sales.col.date')}</Th>
                                                <Th>{t('sales.col.items')}</Th>
                                                <Th right>
                                                    {t('sales.col.total')}
                                                </Th>
                                                <Th>{t('sales.col.status')}</Th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {selected.sales.map((sale) => (
                                                <tr key={sale.id}>
                                                    <Td className="font-bold num">
                                                        <Link
                                                            href={showSale(
                                                                sale.id,
                                                            )}
                                                            className="text-primary underline-offset-4 hover:underline"
                                                        >
                                                            {sale.number}
                                                        </Link>
                                                    </Td>
                                                    <Td className="text-[13px] num whitespace-nowrap">
                                                        {sale.date}
                                                    </Td>
                                                    <Td>{sale.items}</Td>
                                                    <Td
                                                        right
                                                        className="font-bold num whitespace-nowrap"
                                                    >
                                                        {sale.total_label}
                                                    </Td>
                                                    <Td>
                                                        <Chip
                                                            tone={
                                                                saleStatusTone[
                                                                    sale.status
                                                                ]
                                                            }
                                                        >
                                                            {sale.status_label}
                                                        </Chip>
                                                    </Td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </DataTable>
                                </Panel>
                            )}
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

Customers.layout = {
    breadcrumbs: [{ title: t('customers.title'), href: index() }],
    subtitle: t('customers.subtitle'),
};
