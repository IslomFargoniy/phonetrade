import { Head, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import { Chip } from '@/components/chip';
import { DataTable, Td, Th } from '@/components/data-table';
import { Field } from '@/components/field';
import InputError from '@/components/input-error';
import { Panel } from '@/components/panel';
import { Segmented } from '@/components/segmented';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { create, store } from '@/routes/receipts';

type PurchaseType = 'supplier' | 'opening' | 'trade_in';
type Currency = 'UZS' | 'USD';

type ProductOption = {
    id: number;
    name: string;
    image_url: string | null;
    tracking: 'serial' | 'quantity';
    default_warranty_months: number;
    default_max_discount_pct: string;
    attributes: {
        name: string;
        type: 'select' | 'number' | 'text';
        options: string[];
    }[];
};

type CashOption = {
    value: string;
    account_id: number;
    currency: Currency;
    label: string;
};

type Unit = { imei1: string; imei2: string };

type Props = {
    types: { value: PurchaseType; label: string }[];
    products: ProductOption[];
    suppliers: { id: number; name: string }[];
    cashOptions: CashOption[];
    rate: string | null;
};

const currencies = [
    { value: 'USD', label: 'Dollar' },
    { value: 'UZS', label: "So'm" },
] as const;

/** Skaner yoki Excel'dan kelgan matnni donalarga ajratadi: har qatorda IMEI 1 va ixtiyoriy IMEI 2. */
function parseUnits(text: string): Unit[] {
    return text
        .split(/\r?\n/)
        .map((line) => line.split(/[\s,;]+/).filter(Boolean))
        .filter((parts) => parts.length > 0)
        .map((parts) => ({
            imei1: parts[0].replace(/\D/g, ''),
            imei2: (parts[1] ?? '').replace(/\D/g, ''),
        }))
        .filter((unit) => unit.imei1 !== '');
}

export default function ReceiptCreate({
    types,
    products,
    suppliers,
    cashOptions,
    rate,
}: Props) {
    const form = useForm({
        type: types[0].value,
        currency: 'USD' as Currency,
        supplier_id: suppliers[0] ? String(suppliers[0].id) : '',
        customer_phone: '',
        customer_name: '',
        cash_option: cashOptions[0]?.value ?? '',
        product_id: products[0] ? String(products[0].id) : '',
        attributes: {} as Record<string, string>,
        unit_cost: '',
        sale_price: '',
        sale_currency: 'UZS' as Currency,
        max_discount_pct: products[0]?.default_max_discount_pct ?? '0',
        warranty_months: String(products[0]?.default_warranty_months ?? 0),
        qty: '1',
        barcode: '',
        units: [] as Unit[],
    });
    const [scan, setScan] = useState('');
    const [paste, setPaste] = useState('');
    const [pasting, setPasting] = useState(false);

    const { data } = form;
    const errors = form.errors as Record<string, string>;
    const product = products.find((p) => String(p.id) === data.product_id);
    const serial = product?.tracking === 'serial';
    const tradeIn = data.type === 'trade_in';
    const cashOption = cashOptions.find((o) => o.value === data.cash_option);
    const costCurrency: Currency = tradeIn
        ? (cashOption?.currency ?? 'UZS')
        : data.currency;
    const quantity = serial ? data.units.length : Number(data.qty) || 0;
    const total = quantity * (Number(data.unit_cost) || 0);
    const lineErrors = Object.entries(errors)
        .filter(([key]) => key === 'lines' || key.startsWith('lines.'))
        .map(([, message]) => message);

    const pickProduct = (id: string) => {
        const next = products.find((p) => String(p.id) === id);

        form.setData({
            ...data,
            product_id: id,
            attributes: {},
            units: [],
            max_discount_pct: next?.default_max_discount_pct ?? '0',
            warranty_months: String(next?.default_warranty_months ?? 0),
        });
    };

    const addUnits = (units: Unit[]) => {
        const known = new Set(
            data.units.flatMap((u) => [u.imei1, u.imei2]).filter(Boolean),
        );
        const fresh = units.filter((u) => !known.has(u.imei1));

        form.setData('units', [...data.units, ...fresh]);
    };

    const onScanKey = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key !== 'Enter') {
            return;
        }

        // Skaner Enter yuboradi: forma yuborilib ketmasligi kerak.
        event.preventDefault();
        addUnits(parseUnits(scan));
        setScan('');
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((values) => ({
            type: values.type,
            currency: costCurrency,
            supplier_id: values.type === 'supplier' ? values.supplier_id : null,
            customer_phone: tradeIn ? values.customer_phone : null,
            customer_name: tradeIn ? values.customer_name : null,
            cash_account_id: tradeIn ? (cashOption?.account_id ?? null) : null,
            lines: [
                {
                    product_id: values.product_id,
                    attributes: values.attributes,
                    unit_cost: values.unit_cost,
                    sale_price: values.sale_price,
                    sale_currency: values.sale_currency,
                    max_discount_pct: values.max_discount_pct,
                    warranty_months: values.warranty_months,
                    units: serial ? values.units : [],
                    qty: serial ? null : values.qty,
                    barcode: serial ? null : values.barcode,
                },
            ],
        }));

        form.post(store().url, { preserveScroll: true });
    };

    if (products.length === 0) {
        return (
            <>
                <Head title={t('receipt.title')} />
                <Panel title={t('receipt.no_products')}>
                    <p className="text-sm text-ink-mid">
                        {t('receipt.no_products.description')}
                    </p>
                </Panel>
            </>
        );
    }

    return (
        <>
            <Head title={t('receipt.title')} />

            <form onSubmit={submit} className="flex flex-col gap-4">
                {types.length > 1 && (
                    <Segmented
                        label={t('receipt.type')}
                        options={types}
                        value={data.type}
                        onChange={(value) => form.setData('type', value)}
                    />
                )}

                <div className="flex flex-wrap items-start gap-4">
                    <Panel className="min-w-0 flex-[1_1_360px]">
                        {data.type === 'supplier' && (
                            <SelectField
                                label={t('receipt.supplier')}
                                name="supplier_id"
                                value={data.supplier_id}
                                onChange={(e) =>
                                    form.setData('supplier_id', e.target.value)
                                }
                                error={errors.supplier_id}
                            >
                                <option value="">
                                    {t('receipt.supplier.choose')}
                                </option>
                                {suppliers.map((supplier) => (
                                    <option
                                        key={supplier.id}
                                        value={supplier.id}
                                    >
                                        {supplier.name}
                                    </option>
                                ))}
                            </SelectField>
                        )}

                        {data.type === 'opening' && (
                            <p className="glass-inner px-3.5 py-3 text-[13px] leading-relaxed text-ink-mid">
                                {t('receipt.opening.hint')}
                            </p>
                        )}

                        {tradeIn && (
                            <div className="flex flex-col gap-3 rounded-[18px] bg-warning-bg/70 p-3.5">
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <Field
                                        label={t('receipt.customer.phone')}
                                        name="customer_phone"
                                        type="tel"
                                        inputMode="tel"
                                        required
                                        placeholder="+998 90 123 45 67"
                                        value={data.customer_phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'customer_phone',
                                                e.target.value,
                                            )
                                        }
                                        error={errors.customer_phone}
                                    />
                                    <Field
                                        label={t('receipt.customer.name')}
                                        name="customer_name"
                                        value={data.customer_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'customer_name',
                                                e.target.value,
                                            )
                                        }
                                        error={errors.customer_name}
                                    />
                                </div>
                                <SelectField
                                    label={t('receipt.cash')}
                                    name="cash_option"
                                    value={data.cash_option}
                                    onChange={(e) =>
                                        form.setData(
                                            'cash_option',
                                            e.target.value,
                                        )
                                    }
                                    error={errors.cash_account_id}
                                >
                                    {cashOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </SelectField>
                                <p className="text-[13px] leading-relaxed text-warning">
                                    {t('receipt.trade_in.hint')}
                                </p>
                            </div>
                        )}

                        <SelectField
                            label={t('receipt.product')}
                            name="product_id"
                            value={data.product_id}
                            onChange={(e) => pickProduct(e.target.value)}
                        >
                            {products.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </SelectField>

                        {product && product.attributes.length > 0 && (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {product.attributes.map((attribute) =>
                                    attribute.type === 'select' ? (
                                        <SelectField
                                            key={attribute.name}
                                            label={attribute.name}
                                            name={`attr-${attribute.name}`}
                                            value={
                                                data.attributes[
                                                    attribute.name
                                                ] ?? ''
                                            }
                                            onChange={(e) =>
                                                form.setData('attributes', {
                                                    ...data.attributes,
                                                    [attribute.name]:
                                                        e.target.value,
                                                })
                                            }
                                        >
                                            <option value="">—</option>
                                            {attribute.options.map((option) => (
                                                <option
                                                    key={option}
                                                    value={option}
                                                >
                                                    {option}
                                                </option>
                                            ))}
                                        </SelectField>
                                    ) : (
                                        <Field
                                            key={attribute.name}
                                            label={attribute.name}
                                            name={`attr-${attribute.name}`}
                                            inputMode={
                                                attribute.type === 'number'
                                                    ? 'decimal'
                                                    : undefined
                                            }
                                            value={
                                                data.attributes[
                                                    attribute.name
                                                ] ?? ''
                                            }
                                            onChange={(e) =>
                                                form.setData('attributes', {
                                                    ...data.attributes,
                                                    [attribute.name]:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    ),
                                )}
                            </div>
                        )}

                        {!tradeIn && (
                            <div className="flex flex-col gap-1.5">
                                <span className="text-xs font-semibold text-muted-foreground">
                                    {t('receipt.cost_currency')}
                                </span>
                                <Segmented
                                    label={t('receipt.cost_currency')}
                                    options={currencies}
                                    value={data.currency}
                                    onChange={(value) =>
                                        form.setData('currency', value)
                                    }
                                />
                                <InputError message={errors.currency} />
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-3">
                            <Field
                                label={t('receipt.unit_cost', {
                                    currency:
                                        costCurrency === 'USD' ? '$' : "so'm",
                                })}
                                name="unit_cost"
                                inputMode="decimal"
                                required
                                className="num"
                                value={data.unit_cost}
                                onChange={(e) =>
                                    form.setData('unit_cost', e.target.value)
                                }
                                error={errors['lines.0.unit_cost']}
                            />
                            <Field
                                label={t('receipt.sale_price')}
                                name="sale_price"
                                inputMode="decimal"
                                required
                                className="num"
                                value={data.sale_price}
                                onChange={(e) =>
                                    form.setData('sale_price', e.target.value)
                                }
                                error={errors['lines.0.sale_price']}
                            />
                            <SelectField
                                label={t('receipt.sale_currency')}
                                name="sale_currency"
                                value={data.sale_currency}
                                onChange={(e) =>
                                    form.setData(
                                        'sale_currency',
                                        e.target.value as Currency,
                                    )
                                }
                            >
                                <option value="UZS">So'm</option>
                                <option value="USD">Dollar</option>
                            </SelectField>
                            <Field
                                label={t('receipt.max_discount')}
                                name="max_discount_pct"
                                inputMode="decimal"
                                className="num"
                                value={data.max_discount_pct}
                                onChange={(e) =>
                                    form.setData(
                                        'max_discount_pct',
                                        e.target.value,
                                    )
                                }
                                error={errors['lines.0.max_discount_pct']}
                            />
                            <Field
                                label={t('receipt.warranty')}
                                name="warranty_months"
                                inputMode="numeric"
                                className="num"
                                value={data.warranty_months}
                                onChange={(e) =>
                                    form.setData(
                                        'warranty_months',
                                        e.target.value,
                                    )
                                }
                                error={errors['lines.0.warranty_months']}
                            />
                            {!serial && (
                                <Field
                                    label={t('receipt.qty')}
                                    name="qty"
                                    inputMode="numeric"
                                    required
                                    className="num"
                                    value={data.qty}
                                    onChange={(e) =>
                                        form.setData('qty', e.target.value)
                                    }
                                    error={errors['lines.0.qty']}
                                />
                            )}
                        </div>

                        {!serial && (
                            <Field
                                label={t('receipt.barcode')}
                                name="barcode"
                                className="num"
                                hint={t('receipt.barcode.hint')}
                                value={data.barcode}
                                onChange={(e) =>
                                    form.setData('barcode', e.target.value)
                                }
                            />
                        )}

                        <p className="text-xs text-muted-foreground">
                            {rate
                                ? t('receipt.rate', {
                                      rate: formatNumber(rate),
                                  })
                                : t('receipt.rate.missing')}
                        </p>
                    </Panel>

                    <Panel
                        title={
                            serial ? t('receipt.units') : t('receipt.summary')
                        }
                        action={
                            serial ? (
                                <Chip tone="info">
                                    {t('receipt.units.count', {
                                        count: data.units.length,
                                    })}
                                </Chip>
                            ) : undefined
                        }
                        className="min-w-0 flex-[999_1_480px]"
                    >
                        {serial && (
                            <>
                                <div className="flex flex-wrap items-end gap-2">
                                    <Field
                                        label={t('receipt.scan')}
                                        name="scan"
                                        inputMode="numeric"
                                        autoComplete="off"
                                        className="num"
                                        placeholder={t(
                                            'receipt.scan.placeholder',
                                        )}
                                        hint={t('receipt.scan.hint')}
                                        value={scan}
                                        onChange={(e) =>
                                            setScan(e.target.value)
                                        }
                                        onKeyDown={onScanKey}
                                        wrapperClassName="flex-1 basis-60"
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setPasting(!pasting)}
                                    >
                                        {t('receipt.paste')}
                                    </Button>
                                </div>

                                {pasting && (
                                    <div className="flex flex-col gap-2">
                                        <label
                                            htmlFor="paste"
                                            className="text-xs font-semibold text-muted-foreground"
                                        >
                                            {t('receipt.paste.label')}
                                        </label>
                                        <textarea
                                            id="paste"
                                            rows={5}
                                            value={paste}
                                            onChange={(e) =>
                                                setPaste(e.target.value)
                                            }
                                            className="w-full rounded-[14px] border border-white/90 bg-white/72 p-3 text-sm num text-ink shadow-[inset_0_1px_2px_rgb(28_35_64/0.07)] outline-none focus-visible:ring-[3px] focus-visible:ring-ring"
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            className="self-start"
                                            onClick={() => {
                                                addUnits(parseUnits(paste));
                                                setPaste('');
                                                setPasting(false);
                                            }}
                                        >
                                            {t('action.add')}
                                        </Button>
                                    </div>
                                )}

                                {data.units.length > 0 && (
                                    <DataTable minWidth={420}>
                                        <thead>
                                            <tr>
                                                <Th>№</Th>
                                                <Th>IMEI 1</Th>
                                                <Th>IMEI 2</Th>
                                                <Th />
                                            </tr>
                                        </thead>
                                        <tbody className="num">
                                            {data.units.map((unit, i) => (
                                                <tr key={unit.imei1}>
                                                    <Td className="text-muted-foreground">
                                                        {i + 1}
                                                    </Td>
                                                    <Td>{unit.imei1}</Td>
                                                    <Td>{unit.imei2}</Td>
                                                    <Td right className="py-1">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={t(
                                                                'receipt.units.remove',
                                                            )}
                                                            onClick={() =>
                                                                form.setData(
                                                                    'units',
                                                                    data.units.filter(
                                                                        (
                                                                            _,
                                                                            index,
                                                                        ) =>
                                                                            index !==
                                                                            i,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </Td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </DataTable>
                                )}
                            </>
                        )}

                        {lineErrors.length > 0 && (
                            <div
                                role="alert"
                                className="rounded-[18px] bg-danger-bg px-3.5 py-3 text-sm font-semibold text-danger"
                            >
                                {lineErrors.map((message) => (
                                    <p key={message}>{message}</p>
                                ))}
                            </div>
                        )}

                        <div className="mt-auto flex flex-wrap items-center justify-between gap-3">
                            <span className="text-sm text-muted-foreground">
                                {t('receipt.total', { count: quantity })}{' '}
                                <strong className="num text-ink">
                                    {costCurrency === 'USD'
                                        ? `$ ${formatNumber(total)}`
                                        : `${formatNumber(total)} so'm`}
                                </strong>
                            </span>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing || quantity === 0}
                            >
                                {form.processing && <Spinner />}
                                {t('receipt.save')}
                            </Button>
                        </div>
                    </Panel>
                </div>
            </form>
        </>
    );
}

ReceiptCreate.layout = {
    breadcrumbs: [{ title: t('receipt.title'), href: create() }],
    subtitle: t('receipt.subtitle'),
};
