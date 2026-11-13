import { Head, router, usePage } from '@inertiajs/react';
import { Minus, Plus, ScanLine, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { AttributeChips } from '@/components/attribute-chips';
import { Chip } from '@/components/chip';
import { Field } from '@/components/field';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber } from '@/lib/format';
import { getJson, postJson } from '@/lib/http';
import { t } from '@/lib/i18n';
import { index, lookup, preview } from '@/routes/pos';
import { store } from '@/routes/sales';
import type { StockUnitRow, StockVariantRow } from '@/types';

type Currency = 'UZS' | 'USD';
type Method = 'cash' | 'card' | 'transfer';

type CartItem = {
    key: string;
    unitId: number | null;
    variantId: number | null;
    name: string;
    attributes: Record<string, string>;
    code: string;
    priceLabel: string;
    maxDiscountPct: string;
    warrantyMonths: number | null;
    qty: number;
    maxQty: number;
    discount: string;
};

type Totals = {
    currency: Currency;
    mixed: boolean;
    subtotal_label: string;
    discount: string;
    discount_label: string;
    total: string;
    total_label: string;
    lines: {
        final_label: string;
        net_label: string;
        max_discount: string;
        max_discount_label: string;
    }[];
};

type LookupResult = {
    exact: boolean;
    units: StockUnitRow[];
    variants: StockVariantRow[];
};

const methods: { value: Method; label: string }[] = [
    { value: 'cash', label: 'Naqd' },
    { value: 'card', label: 'Karta' },
    { value: 'transfer', label: "O'tkazma" },
];
const currencies: Currency[] = ['UZS', 'USD'];

function money(amount: number, currency: Currency): string {
    const rounded = Math.round(amount * 100) / 100;

    return currency === 'USD'
        ? `$ ${formatNumber(rounded)}`
        : `${formatNumber(rounded)} so'm`;
}

export default function Pos({ rate }: { rate: string | null }) {
    const pageErrors = usePage().props.errors as Record<string, string>;
    const [code, setCode] = useState('');
    const [searching, setSearching] = useState(false);
    const [results, setResults] = useState<LookupResult | null>(null);
    const [cart, setCart] = useState<CartItem[]>([]);
    const [totals, setTotals] = useState<Totals | null>(null);
    const [cartError, setCartError] = useState('');
    const [payments, setPayments] = useState<Record<string, string>>({});
    const [customerPhone, setCustomerPhone] = useState('');
    const [customerName, setCustomerName] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const scanRef = useRef<HTMLInputElement>(null);

    const payloadItems = cart.map((item) => ({
        stock_unit_id: item.unitId,
        variant_id: item.variantId,
        qty: item.qty,
        discount: item.discount === '' ? '0' : item.discount,
    }));
    const cartSignature = JSON.stringify(payloadItems);

    // Savat o'zgarganda jami serverda qayta hisoblanadi: ekrandagi raqam saqlanadigan raqam bilan bir xil.
    useEffect(() => {
        if (cart.length === 0) {
            setTotals(null);
            setCartError('');

            return;
        }

        let cancelled = false;
        const timer = setTimeout(() => {
            void postJson<Totals>(preview().url, {
                items: JSON.parse(cartSignature) as unknown,
            }).then((result) => {
                if (cancelled) {
                    return;
                }

                if (result.ok) {
                    setTotals(result.data);
                    setCartError('');
                } else {
                    setCartError(result.message || t('pos.error.preview'));
                }
            });
        }, 200);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cartSignature]);

    const addUnit = (unit: StockUnitRow) => {
        setCart((current) =>
            current.some((item) => item.unitId === unit.id)
                ? current
                : [
                      ...current,
                      {
                          key: `u${unit.id}`,
                          unitId: unit.id,
                          variantId: null,
                          name: unit.product,
                          attributes: unit.attributes,
                          code: `IMEI ${unit.imei1}`,
                          priceLabel: unit.sale_price_label,
                          maxDiscountPct: unit.max_discount_pct,
                          warrantyMonths: unit.warranty_months,
                          qty: 1,
                          maxQty: 1,
                          discount: '',
                      },
                  ],
        );
    };

    const addVariant = (variant: StockVariantRow) => {
        setCart((current) => {
            const existing = current.find(
                (item) => item.variantId === variant.id,
            );

            if (existing) {
                return current.map((item) =>
                    item === existing
                        ? {
                              ...item,
                              qty: Math.min(item.qty + 1, item.maxQty),
                          }
                        : item,
                );
            }

            return [
                ...current,
                {
                    key: `v${variant.id}`,
                    unitId: null,
                    variantId: variant.id,
                    name: variant.product,
                    attributes: variant.attributes,
                    code: `Barcode ${variant.barcode}`,
                    priceLabel: variant.sale_price_label,
                    maxDiscountPct: variant.max_discount_pct,
                    warrantyMonths: null,
                    qty: 1,
                    maxQty: variant.qty,
                    discount: '',
                },
            ];
        });
    };

    const search = async () => {
        const value = code.trim();

        if (value === '') {
            return;
        }

        setSearching(true);
        const result = await getJson<LookupResult>(
            lookup({ query: { code: value } }).url,
        );
        setSearching(false);

        if (!result.ok) {
            setResults({ exact: false, units: [], variants: [] });

            return;
        }

        const { exact, units, variants } = result.data;

        // Skaner aniq topgan tovar darhol savatga tushadi.
        if (exact && units.length + variants.length === 1) {
            if (units[0]) {
                addUnit(units[0]);
            } else {
                addVariant(variants[0]);
            }

            setCode('');
            setResults(null);
            scanRef.current?.focus();

            return;
        }

        setResults(result.data);
    };

    const onScanKey = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            void search();
        }
    };

    const update = (key: string, patch: Partial<CartItem>) =>
        setCart((current) =>
            current.map((item) =>
                item.key === key ? { ...item, ...patch } : item,
            ),
        );

    const saleCurrency: Currency = totals?.currency ?? 'UZS';
    const total = totals ? Number(totals.total) : 0;
    const rateValue = rate ? Number(rate) : 0;

    // Har to'lovni savdo valyutasiga keltirib yig'adi (ekranda ko'rsatish uchun; yakuniy tekshiruv serverda).
    const paid = methods.reduce(
        (sum, method) =>
            currencies.reduce((inner, currency) => {
                const amount =
                    Number(payments[`${method.value}:${currency}`]) || 0;

                if (currency === saleCurrency) {
                    return inner + amount;
                }

                if (rateValue === 0) {
                    return inner;
                }

                return (
                    inner +
                    (currency === 'USD'
                        ? amount * rateValue
                        : amount / rateValue)
                );
            }, sum),
        0,
    );
    const remaining = Math.round((total - paid) * 100) / 100;

    const submit = () => {
        setSubmitting(true);

        router.post(
            store().url,
            {
                items: payloadItems,
                payments: methods.flatMap((method) =>
                    currencies.map((currency) => ({
                        method: method.value,
                        currency,
                        amount: payments[`${method.value}:${currency}`] ?? '',
                    })),
                ),
                customer_phone: customerPhone,
                customer_name: customerName,
            },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const errorMessages = [
        cartError,
        ...Object.entries(pageErrors)
            .filter(([key]) => !key.startsWith('customer_'))
            .map(([, message]) => message),
    ].filter(Boolean);

    return (
        <>
            <Head title={t('pos.title')} />

            <div className="flex flex-wrap items-start gap-4">
                <Panel className="min-w-0 flex-[999_1_520px]">
                    <label
                        htmlFor="scan"
                        className="text-xs font-semibold text-muted-foreground"
                    >
                        {t('pos.scan')}
                    </label>
                    <div className="flex items-center gap-2.5 rounded-full border border-white bg-white/80 py-1 pr-1.5 pl-4 shadow-[inset_0_1px_3px_rgb(28_35_64/0.08),0_0_0_4px_rgb(0_88_194/0.12)]">
                        <ScanLine
                            className="size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <Input
                            id="scan"
                            ref={scanRef}
                            autoFocus
                            autoComplete="off"
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                            onKeyDown={onScanKey}
                            placeholder={t('pos.scan.placeholder')}
                            className="border-0 bg-transparent px-0 text-base shadow-none focus-visible:ring-0"
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={searching}
                            onClick={() => void search()}
                        >
                            {searching && <Spinner />}
                            {t('pos.find')}
                        </Button>
                    </div>

                    {results && (
                        <div className="flex flex-col glass-inner px-3.5 py-1">
                            {results.units.length + results.variants.length ===
                                0 && (
                                <p className="py-3 text-sm text-ink-mid">
                                    {t('pos.not_found')}
                                </p>
                            )}
                            {results.units.map((unit) => (
                                <button
                                    key={unit.id}
                                    type="button"
                                    onClick={() => {
                                        addUnit(unit);
                                        setResults(null);
                                        setCode('');
                                    }}
                                    className="flex min-h-14 cursor-pointer items-center justify-between gap-3 border-t border-border py-2 text-left first:border-t-0"
                                >
                                    <span className="flex min-w-0 flex-col">
                                        <span className="font-bold">
                                            {unit.product}
                                        </span>
                                        <span className="text-xs num text-muted-foreground">
                                            {[
                                                ...Object.values(
                                                    unit.attributes,
                                                ),
                                                unit.imei1,
                                            ].join(' · ')}
                                        </span>
                                    </span>
                                    <span className="font-bold num">
                                        {unit.sale_price_label}
                                    </span>
                                </button>
                            ))}
                            {results.variants.map((variant) => (
                                <button
                                    key={variant.id}
                                    type="button"
                                    onClick={() => {
                                        addVariant(variant);
                                        setResults(null);
                                        setCode('');
                                    }}
                                    className="flex min-h-14 cursor-pointer items-center justify-between gap-3 border-t border-border py-2 text-left first:border-t-0"
                                >
                                    <span className="flex min-w-0 flex-col">
                                        <span className="font-bold">
                                            {variant.product}
                                        </span>
                                        <span className="text-xs num text-muted-foreground">
                                            {t('pos.in_stock', {
                                                count: variant.qty,
                                            })}
                                        </span>
                                    </span>
                                    <span className="font-bold num">
                                        {variant.sale_price_label}
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}

                    {cart.length === 0 ? (
                        <p className="py-6 text-center text-sm text-ink-mid">
                            {t('pos.empty')}
                        </p>
                    ) : (
                        cart.map((item, i) => {
                            const line = totals?.lines[i];

                            return (
                                <div
                                    key={item.key}
                                    className="flex flex-wrap items-center gap-3.5 glass-inner p-4"
                                >
                                    <div className="flex min-w-0 flex-[1_1_240px] flex-col gap-1.5">
                                        <span className="text-base font-bold">
                                            {item.name}
                                        </span>
                                        <AttributeChips
                                            attributes={item.attributes}
                                        />
                                        <span className="text-xs num text-muted-foreground">
                                            {[
                                                item.code,
                                                item.warrantyMonths
                                                    ? t('pos.warranty', {
                                                          months: item.warrantyMonths,
                                                      })
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    </div>

                                    {item.variantId !== null && (
                                        <div className="flex items-center gap-1 rounded-full bg-muted p-1">
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="icon"
                                                className="size-9"
                                                aria-label={t('pos.qty.down')}
                                                disabled={item.qty <= 1}
                                                onClick={() =>
                                                    update(item.key, {
                                                        qty: item.qty - 1,
                                                    })
                                                }
                                            >
                                                <Minus />
                                            </Button>
                                            <span className="min-w-7 text-center font-bold num">
                                                {item.qty}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="icon"
                                                className="size-9"
                                                aria-label={t('pos.qty.up')}
                                                disabled={
                                                    item.qty >= item.maxQty
                                                }
                                                onClick={() =>
                                                    update(item.key, {
                                                        qty: item.qty + 1,
                                                    })
                                                }
                                            >
                                                <Plus />
                                            </Button>
                                        </div>
                                    )}

                                    <div className="flex w-32 flex-col gap-1">
                                        <label
                                            htmlFor={`discount-${item.key}`}
                                            className="text-xs font-semibold text-muted-foreground"
                                        >
                                            {t('pos.discount')}
                                        </label>
                                        <Input
                                            id={`discount-${item.key}`}
                                            inputMode="decimal"
                                            placeholder="0"
                                            className="text-right num"
                                            value={item.discount}
                                            onChange={(event) =>
                                                update(item.key, {
                                                    discount:
                                                        event.target.value.replace(
                                                            /[^\d.]/g,
                                                            '',
                                                        ),
                                                })
                                            }
                                        />
                                        <span className="text-[11px] num text-muted-foreground">
                                            {line
                                                ? t('pos.discount.max', {
                                                      amount: line.max_discount_label,
                                                  })
                                                : `max ${item.maxDiscountPct}%`}
                                        </span>
                                    </div>

                                    <div className="flex min-w-28 flex-col items-end gap-1">
                                        <span className="text-lg font-extrabold num">
                                            {line?.net_label ?? item.priceLabel}
                                        </span>
                                        {item.qty > 1 && (
                                            <span className="text-xs num text-muted-foreground">
                                                {item.qty} × {item.priceLabel}
                                            </span>
                                        )}
                                    </div>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('pos.remove', {
                                            name: item.name,
                                        })}
                                        onClick={() =>
                                            setCart((current) =>
                                                current.filter(
                                                    (other) =>
                                                        other.key !== item.key,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            );
                        })
                    )}
                </Panel>

                <aside className="flex min-w-0 flex-[1_1_340px] flex-col gap-4">
                    <Panel className="gap-1.5">
                        <span className="text-xs font-semibold text-muted-foreground">
                            {t('pos.total')}
                        </span>
                        <span className="text-4xl font-extrabold num tracking-tight">
                            {totals?.total_label ?? money(0, 'UZS')}
                        </span>
                        {totals && Number(totals.discount) > 0 && (
                            <span className="text-[13px] num text-muted-foreground">
                                {t('pos.subtotal', {
                                    subtotal: totals.subtotal_label,
                                    discount: totals.discount_label,
                                })}
                            </span>
                        )}
                        {totals?.mixed && rate && (
                            <Chip tone="info" className="mt-1.5 self-start">
                                {t('pos.mixed', { rate: formatNumber(rate) })}
                            </Chip>
                        )}
                    </Panel>

                    <Panel>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Field
                                label={t('pos.customer.phone')}
                                name="customer_phone"
                                type="tel"
                                inputMode="tel"
                                placeholder="+998 __ ___ __ __"
                                value={customerPhone}
                                onChange={(event) =>
                                    setCustomerPhone(event.target.value)
                                }
                                error={pageErrors.customer_phone}
                            />
                            <Field
                                label={t('pos.customer.name')}
                                name="customer_name"
                                value={customerName}
                                onChange={(event) =>
                                    setCustomerName(event.target.value)
                                }
                                error={pageErrors.customer_name}
                            />
                        </div>

                        <div className="grid grid-cols-[72px_minmax(0,1fr)_minmax(0,92px)] items-center gap-2">
                            <span />
                            <span className="text-right text-xs font-semibold text-muted-foreground">
                                So'm
                            </span>
                            <span className="text-right text-xs font-semibold text-muted-foreground">
                                Dollar
                            </span>
                            {methods.map((method) => (
                                <div key={method.value} className="contents">
                                    <span className="text-sm font-semibold">
                                        {method.label}
                                    </span>
                                    {currencies.map((currency) => {
                                        const key = `${method.value}:${currency}`;

                                        return (
                                            <Input
                                                key={key}
                                                aria-label={`${method.label}, ${currency === 'USD' ? 'dollar' : "so'm"}`}
                                                inputMode="decimal"
                                                placeholder="0"
                                                className="text-right num"
                                                value={payments[key] ?? ''}
                                                onChange={(event) =>
                                                    setPayments({
                                                        ...payments,
                                                        [key]: event.target.value.replace(
                                                            /[^\d.]/g,
                                                            '',
                                                        ),
                                                    })
                                                }
                                            />
                                        );
                                    })}
                                </div>
                            ))}
                        </div>

                        <div
                            className={
                                remaining > 0
                                    ? 'flex justify-between rounded-full bg-warning-bg px-4 py-3 text-sm font-bold text-warning'
                                    : 'flex justify-between rounded-full bg-success-bg px-4 py-3 text-sm font-bold text-success'
                            }
                        >
                            <span>
                                {remaining < 0
                                    ? t('pos.change')
                                    : t('pos.remaining')}
                            </span>
                            <span className="num">
                                {money(Math.abs(remaining), saleCurrency)}
                            </span>
                        </div>

                        {errorMessages.length > 0 && (
                            <div
                                role="alert"
                                className="rounded-[18px] bg-danger-bg px-3.5 py-3 text-sm font-semibold text-danger"
                            >
                                {errorMessages.map((message) => (
                                    <p key={message}>{message}</p>
                                ))}
                            </div>
                        )}

                        <Button
                            type="button"
                            size="lg"
                            disabled={
                                submitting ||
                                cart.length === 0 ||
                                !totals ||
                                remaining > 0
                            }
                            onClick={submit}
                        >
                            {submitting && <Spinner />}
                            {t('pos.close')}
                        </Button>
                    </Panel>
                </aside>
            </div>
        </>
    );
}

Pos.layout = {
    breadcrumbs: [{ title: t('pos.title'), href: index() }],
};
