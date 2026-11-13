import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AttributeChips } from '@/components/attribute-chips';
import { Chip } from '@/components/chip';
import type { ChipTone } from '@/components/chip';
import { DataTable, Td, Th } from '@/components/data-table';
import { Field } from '@/components/field';
import { Panel } from '@/components/panel';
import { ProductImage } from '@/components/product-image';
import { UnitPhotos } from '@/components/unit-photos';
import { Segmented } from '@/components/segmented';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index } from '@/routes/stock';
import {
    restock as restockUnit,
    update as updateUnit,
} from '@/routes/stock/units';
import { store as storeTransfer } from '@/routes/transfers';
import type { BranchOption, StockUnitRow, StockVariantRow } from '@/types';

type Kind = 'all' | 'phone' | 'accessory';
type Scope = 'branch' | 'all';

type Props = {
    filters: { q: string; kind: Kind; status: string; scope: Scope };
    statuses: { value: string; label: string }[];
    units: StockUnitRow[];
    variants: StockVariantRow[];
    canSeeCost: boolean;
    canEdit: boolean;
    transferBranches: BranchOption[];
    allBranches: BranchOption[];
};

const statusTone: Record<string, ChipTone> = {
    in_stock: 'success',
    reserved: 'info',
    sold: 'neutral',
    returned: 'warning',
    in_transit: 'warning',
};

export default function StockIndex({
    filters,
    statuses,
    units,
    variants,
    canSeeCost,
    canEdit,
    transferBranches,
    allBranches,
}: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [checked, setChecked] = useState<number[]>([]);
    const selected = units.find((unit) => unit.id === selectedId) ?? null;
    const destinations = canEdit ? allBranches : transferBranches;

    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            index().url,
            { ...filters, q: search, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const toggle = (id: number) =>
        setChecked((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );

    return (
        <>
            <Head title={t('stock.title')} />

            <Panel className="flex-row flex-wrap items-center gap-3 py-3.5">
                <form
                    className="min-w-0 flex-[999_1_260px]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({});
                    }}
                >
                    <Input
                        aria-label={t('stock.search')}
                        placeholder={t('stock.search.placeholder')}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        autoFocus
                    />
                </form>
                <Segmented
                    label={t('stock.kind')}
                    className="self-center"
                    options={[
                        { value: 'all', label: t('stock.kind.all') },
                        { value: 'phone', label: t('stock.kind.phone') },
                        {
                            value: 'accessory',
                            label: t('stock.kind.accessory'),
                        },
                    ]}
                    value={filters.kind}
                    onChange={(kind) => apply({ kind })}
                />
                <select
                    aria-label={t('stock.status')}
                    value={filters.status}
                    onChange={(event) => apply({ status: event.target.value })}
                    className="min-h-11 rounded-[14px] border border-white/90 bg-white/72 px-3 text-[15px] text-ink outline-none focus-visible:ring-[3px] focus-visible:ring-ring"
                >
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </select>
                {canEdit && (
                    <Segmented
                        label={t('stock.scope')}
                        className="self-center"
                        options={[
                            { value: 'branch', label: t('stock.scope.branch') },
                            { value: 'all', label: t('stock.scope.all') },
                        ]}
                        value={filters.scope}
                        onChange={(scope) => apply({ scope })}
                    />
                )}
            </Panel>

            <div className="flex flex-wrap items-start gap-4">
                <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
                    {filters.kind !== 'accessory' && (
                        <Panel
                            title={t('stock.phones', { count: units.length })}
                        >
                            {units.length === 0 ? (
                                <p className="text-sm text-ink-mid">
                                    {t('stock.empty')}
                                </p>
                            ) : (
                                <DataTable minWidth={canSeeCost ? 820 : 700}>
                                    <thead>
                                        <tr>
                                            <Th />
                                            <Th>{t('stock.col.product')}</Th>
                                            <Th>{t('stock.col.codes')}</Th>
                                            {canSeeCost && (
                                                <Th right>
                                                    {t('stock.col.cost')}
                                                </Th>
                                            )}
                                            <Th right>
                                                {t('stock.col.price')}
                                            </Th>
                                            <Th right>
                                                {t('stock.col.discount')}
                                            </Th>
                                            <Th>{t('stock.col.status')}</Th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {units.map((unit) => (
                                            <tr
                                                key={unit.id}
                                                onClick={() =>
                                                    setSelectedId(unit.id)
                                                }
                                                className={cn(
                                                    'cursor-pointer',
                                                    unit.id === selectedId &&
                                                        'bg-info-bg/60',
                                                )}
                                            >
                                                <Td className="w-8">
                                                    {unit.status ===
                                                        'in_stock' &&
                                                        destinations.length >
                                                            0 && (
                                                            <input
                                                                type="checkbox"
                                                                aria-label={t(
                                                                    'stock.select',
                                                                    {
                                                                        code: unit.barcode,
                                                                    },
                                                                )}
                                                                checked={checked.includes(
                                                                    unit.id,
                                                                )}
                                                                onClick={(
                                                                    event,
                                                                ) =>
                                                                    event.stopPropagation()
                                                                }
                                                                onChange={() =>
                                                                    toggle(
                                                                        unit.id,
                                                                    )
                                                                }
                                                                className="size-4 accent-primary"
                                                            />
                                                        )}
                                                </Td>
                                                <Td>
                                                    <ProductImage
                                                        src={unit.image_url}
                                                        name={unit.product}
                                                        className="float-left mr-3 size-12"
                                                    />
                                                    <button
                                                        type="button"
                                                        className="cursor-pointer text-left font-bold"
                                                        onClick={() =>
                                                            setSelectedId(
                                                                unit.id,
                                                            )
                                                        }
                                                    >
                                                        {unit.product}
                                                    </button>
                                                    <div className="mt-1.5">
                                                        <AttributeChips
                                                            attributes={
                                                                unit.attributes
                                                            }
                                                        />
                                                    </div>
                                                </Td>
                                                <Td className="text-[13px] num">
                                                    {unit.imei1}
                                                    <br />
                                                    <span className="font-semibold text-info">
                                                        {unit.barcode}
                                                    </span>
                                                </Td>
                                                {canSeeCost && (
                                                    <Td right className="num">
                                                        {unit.cost_label}
                                                    </Td>
                                                )}
                                                <Td
                                                    right
                                                    className="font-bold num"
                                                >
                                                    {unit.sale_price_label}
                                                </Td>
                                                <Td right className="num">
                                                    {unit.max_discount_pct}%
                                                </Td>
                                                <Td>
                                                    <Chip
                                                        tone={
                                                            statusTone[
                                                                unit.status
                                                            ] ?? 'neutral'
                                                        }
                                                    >
                                                        {unit.status_label}
                                                    </Chip>
                                                </Td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </DataTable>
                            )}
                        </Panel>
                    )}

                    {filters.kind !== 'phone' && variants.length > 0 && (
                        <Panel
                            title={t('stock.accessories', {
                                count: variants.length,
                            })}
                        >
                            <DataTable minWidth={560}>
                                <thead>
                                    <tr>
                                        <Th>{t('stock.col.product')}</Th>
                                        <Th>{t('stock.col.barcode')}</Th>
                                        <Th right>{t('stock.col.price')}</Th>
                                        <Th right>{t('stock.col.qty')}</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {variants.map((variant) => (
                                        <tr key={variant.id}>
                                            <Td>
                                                <ProductImage
                                                    src={variant.image_url}
                                                    name={variant.product}
                                                    kind="accessory"
                                                    className="float-left mr-3 size-12"
                                                />
                                                <div className="font-bold">
                                                    {variant.product}
                                                </div>
                                                <div className="mt-1.5">
                                                    <AttributeChips
                                                        attributes={
                                                            variant.attributes
                                                        }
                                                    />
                                                </div>
                                            </Td>
                                            <Td className="text-[13px] num">
                                                {variant.barcode}
                                            </Td>
                                            <Td right className="font-bold num">
                                                {variant.sale_price_label}
                                            </Td>
                                            <Td right>
                                                <Chip>
                                                    {t('stock.qty', {
                                                        count: variant.qty,
                                                    })}
                                                </Chip>
                                            </Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </DataTable>
                        </Panel>
                    )}
                </div>

                <aside className="flex min-w-0 flex-[1_1_320px] flex-col gap-4">
                    {checked.length > 0 && destinations.length > 0 && (
                        <Panel
                            title={t('stock.transfer.title', {
                                count: checked.length,
                            })}
                            description={t('stock.transfer.description')}
                        >
                            <Form
                                {...storeTransfer.form()}
                                onSuccess={() => setChecked([])}
                                className="flex flex-col gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        {checked.map((id) => (
                                            <input
                                                key={id}
                                                type="hidden"
                                                name="unit_ids[]"
                                                value={id}
                                            />
                                        ))}
                                        <SelectField
                                            label={t('stock.transfer.to')}
                                            name="to_branch_id"
                                            error={
                                                errors.to_branch_id ??
                                                errors.items
                                            }
                                        >
                                            {destinations.map((branch) => (
                                                <option
                                                    key={branch.id}
                                                    value={branch.id}
                                                >
                                                    {branch.name}
                                                </option>
                                            ))}
                                        </SelectField>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            {t('stock.transfer.send')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </Panel>
                    )}

                    {selected ? (
                        <Panel title={selected.product}>
                            <ProductImage
                                src={selected.image_url}
                                name={selected.product}
                                className="size-40 self-center rounded-[22px]"
                            />
                            <div className="flex flex-col gap-1.5">
                                <span className="text-xs font-semibold text-muted-foreground">
                                    {t('photos.title')}
                                </span>
                                <UnitPhotos
                                    unitId={selected.id}
                                    photos={selected.photos}
                                />
                            </div>
                            <AttributeChips attributes={selected.attributes} />
                            <dl className="grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 glass-inner px-4 py-3.5 text-sm">
                                <dt className="text-xs font-semibold text-muted-foreground">
                                    IMEI 1
                                </dt>
                                <dd className="text-right num">
                                    {selected.imei1}
                                </dd>
                                {selected.imei2 && (
                                    <>
                                        <dt className="text-xs font-semibold text-muted-foreground">
                                            IMEI 2
                                        </dt>
                                        <dd className="text-right num">
                                            {selected.imei2}
                                        </dd>
                                    </>
                                )}
                                <dt className="text-xs font-semibold text-muted-foreground">
                                    {t('stock.col.barcode')}
                                </dt>
                                <dd className="text-right font-semibold num text-info">
                                    {selected.barcode}
                                </dd>
                                {canSeeCost && selected.cost_label && (
                                    <>
                                        <dt className="text-xs font-semibold text-muted-foreground">
                                            {t('stock.col.cost')}
                                        </dt>
                                        <dd className="text-right num">
                                            {selected.cost_label}
                                            {selected.cost_rate &&
                                                selected.cost_currency ===
                                                    'USD' &&
                                                ` · ${t('stock.rate', { rate: formatNumber(selected.cost_rate) })}`}
                                        </dd>
                                    </>
                                )}
                                <dt className="text-xs font-semibold text-muted-foreground">
                                    {t('stock.col.price')}
                                </dt>
                                <dd className="text-right font-bold num">
                                    {selected.sale_price_label}
                                </dd>
                                <dt className="text-xs font-semibold text-muted-foreground">
                                    {t('stock.col.warranty')}
                                </dt>
                                <dd className="text-right num">
                                    {t('stock.months', {
                                        count: selected.warranty_months,
                                    })}
                                </dd>
                                <dt className="text-xs font-semibold text-muted-foreground">
                                    {t('stock.col.branch')}
                                </dt>
                                <dd className="text-right">
                                    {selected.branch}
                                </dd>
                                <dt className="text-xs font-semibold text-muted-foreground">
                                    {t('stock.col.received')}
                                </dt>
                                <dd className="text-right num">
                                    {selected.received_at}
                                </dd>
                            </dl>

                            {canEdit && selected.status === 'returned' && (
                                <Button
                                    variant="secondary"
                                    className="self-start"
                                    onClick={() =>
                                        router.post(
                                            restockUnit(selected.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('stock.restock')}
                                </Button>
                            )}

                            {canEdit && selected.status === 'in_stock' && (
                                <Form
                                    key={selected.id}
                                    {...updateUnit.form(selected.id)}
                                    options={{ preserveScroll: true }}
                                    className="flex flex-col gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid grid-cols-2 gap-3">
                                                <Field
                                                    label={t('stock.col.price')}
                                                    name="sale_price"
                                                    inputMode="decimal"
                                                    required
                                                    className="num"
                                                    defaultValue={formatNumber(
                                                        selected.sale_price,
                                                    ).replace(/ /g, '')}
                                                    error={errors.sale_price}
                                                />
                                                <SelectField
                                                    label={t('stock.currency')}
                                                    name="sale_currency"
                                                    defaultValue={
                                                        selected.sale_currency
                                                    }
                                                >
                                                    <option value="UZS">
                                                        So'm
                                                    </option>
                                                    <option value="USD">
                                                        Dollar
                                                    </option>
                                                </SelectField>
                                                <Field
                                                    label={t(
                                                        'stock.col.discount',
                                                    )}
                                                    name="max_discount_pct"
                                                    inputMode="decimal"
                                                    required
                                                    className="num"
                                                    defaultValue={
                                                        selected.max_discount_pct
                                                    }
                                                    error={
                                                        errors.max_discount_pct
                                                    }
                                                />
                                                <Field
                                                    label={t(
                                                        'stock.col.warranty',
                                                    )}
                                                    name="warranty_months"
                                                    inputMode="numeric"
                                                    required
                                                    className="num"
                                                    defaultValue={
                                                        selected.warranty_months
                                                    }
                                                    error={
                                                        errors.warranty_months
                                                    }
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                variant="secondary"
                                                className="self-start"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {t('stock.save_price')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </Panel>
                    ) : (
                        units.length > 0 && (
                            <Panel>
                                <p className="text-sm text-ink-mid">
                                    {t('stock.pick')}
                                </p>
                            </Panel>
                        )
                    )}
                </aside>
            </div>
        </>
    );
}

StockIndex.layout = {
    breadcrumbs: [{ title: t('stock.title'), href: index() }],
};
