import { Form, Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useEffect } from 'react';
import { AttributeChips } from '@/components/attribute-chips';
import { Chip } from '@/components/chip';
import { DataTable, Td, Th } from '@/components/data-table';
import { Field } from '@/components/field';
import InputError from '@/components/input-error';
import { Panel } from '@/components/panel';
import { SaleReceipt } from '@/components/sale-receipt';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { saleStatusTone } from '@/pages/sales/index';
import type { SaleSummary } from '@/pages/sales/index';
import { index as pos } from '@/routes/pos';
import { index, refund } from '@/routes/sales';

type SaleItem = {
    id: number;
    name: string;
    qty: number;
    returned_qty: number;
    returnable_qty: number;
    imei: string | null;
    barcode: string | null;
    attributes: Record<string, string>;
    price_label: string;
    discount_label: string;
    has_discount: boolean;
    final_label: string;
    warranty_until: string | null;
    cost_label?: string;
    profit_label?: string;
};

type SaleDetail = Omit<SaleSummary, 'items'> & {
    branch: string;
    subtotal_label: string;
    discount_label: string;
    has_discount: boolean;
    change_label: string;
    has_change: boolean;
    customer_phone: string | null;
    items: SaleItem[];
    payments: { id: number; label: string; amount_label: string }[];
    profit_label?: string;
    profit_note?: string;
};

type Props = {
    sale: SaleDetail;
    canRefund: boolean;
    autoPrint: boolean;
    refundMethods: { value: string; label: string }[];
    receipt: { name: string; phone: string | null; footer: string | null };
};

export default function SaleShow({
    sale,
    canRefund,
    autoPrint,
    refundMethods,
    receipt,
}: Props) {
    const showProfit = sale.profit_label !== undefined;
    // Nasiyada qator bo'yicha foyda ko'rsatilmaydi: u to'lovlar bilan tan olinadi.
    const showItemProfit = showProfit && sale.type === 'full';
    const returnable = sale.items.filter((item) => item.returnable_qty > 0);
    const installment = sale.type === 'installment';

    // Savdo yopilgandan keyin chek darhol chop etishga chiqadi.
    useEffect(() => {
        if (autoPrint) {
            const timer = setTimeout(() => window.print(), 400);

            return () => clearTimeout(timer);
        }
    }, [autoPrint]);

    return (
        <>
            <Head title={t('sales.show.title', { number: sale.number })} />

            <div className="flex flex-col gap-4 print:hidden">
                <Panel
                    title={t('sales.show.title', { number: sale.number })}
                    description={[
                        sale.date,
                        sale.branch,
                        sale.seller,
                        sale.customer,
                        sale.customer_phone,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            {installment && (
                                <Chip tone="info">{sale.type_label}</Chip>
                            )}
                            <Chip tone={saleStatusTone[sale.status]}>
                                {sale.status_label}
                            </Chip>
                            <Button
                                variant="secondary"
                                onClick={() => window.print()}
                            >
                                <Printer />
                                {t('sales.show.print')}
                            </Button>
                            <Button asChild>
                                <Link href={pos()}>{t('sales.show.new')}</Link>
                            </Button>
                        </div>
                    }
                >
                    <div className="flex flex-wrap items-end gap-x-8 gap-y-2">
                        <div className="flex flex-col">
                            <span className="text-xs font-semibold text-muted-foreground">
                                {t('sales.col.total')}
                            </span>
                            <span className="text-3xl font-extrabold num tracking-tight">
                                {sale.total_label}
                            </span>
                        </div>
                        {sale.has_discount && (
                            <div className="flex flex-col">
                                <span className="text-xs font-semibold text-muted-foreground">
                                    {t('sales.show.discount')}
                                </span>
                                <span className="text-lg font-bold num">
                                    −{sale.discount_label}
                                </span>
                            </div>
                        )}
                        {showProfit && (
                            <div className="flex flex-col">
                                <span className="text-xs font-semibold text-muted-foreground">
                                    {t('sales.show.profit')}
                                </span>
                                <span className="text-lg font-bold num text-success">
                                    {sale.profit_label}
                                </span>
                                {sale.profit_note && (
                                    <span className="text-xs text-muted-foreground">
                                        {sale.profit_note}
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                </Panel>

                <div className="flex flex-wrap items-start gap-4">
                    <Panel
                        title={t('sales.show.items')}
                        className="min-w-0 flex-[999_1_520px]"
                    >
                        <DataTable minWidth={showItemProfit ? 720 : 560}>
                            <thead>
                                <tr>
                                    <Th>{t('stock.col.product')}</Th>
                                    <Th right>{t('sales.col.qty')}</Th>
                                    <Th right>{t('stock.col.price')}</Th>
                                    {showItemProfit && (
                                        <Th right>{t('stock.col.cost')}</Th>
                                    )}
                                    {showItemProfit && (
                                        <Th right>{t('sales.show.profit')}</Th>
                                    )}
                                    <Th right>{t('sales.col.total')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {sale.items.map((item) => (
                                    <tr key={item.id}>
                                        <Td>
                                            <div className="font-bold">
                                                {item.name}
                                            </div>
                                            <div className="my-1.5">
                                                <AttributeChips
                                                    attributes={item.attributes}
                                                />
                                            </div>
                                            <div className="text-xs num text-muted-foreground">
                                                {[
                                                    item.imei
                                                        ? `IMEI ${item.imei}`
                                                        : item.barcode,
                                                    item.warranty_until
                                                        ? t(
                                                              'sales.show.warranty',
                                                              {
                                                                  date: item.warranty_until,
                                                              },
                                                          )
                                                        : null,
                                                    item.returned_qty > 0
                                                        ? t(
                                                              'sales.show.returned',
                                                              {
                                                                  count: item.returned_qty,
                                                              },
                                                          )
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </div>
                                        </Td>
                                        <Td right className="num">
                                            {item.qty}
                                        </Td>
                                        <Td right className="num">
                                            {item.price_label}
                                            {item.has_discount && (
                                                <div className="text-xs text-muted-foreground">
                                                    −{item.discount_label}
                                                </div>
                                            )}
                                        </Td>
                                        {showItemProfit && (
                                            <Td right className="num">
                                                {item.cost_label}
                                            </Td>
                                        )}
                                        {showItemProfit && (
                                            <Td right className="num">
                                                {item.profit_label}
                                            </Td>
                                        )}
                                        <Td right className="font-bold num">
                                            {item.final_label}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </Panel>

                    <aside className="flex min-w-0 flex-[1_1_320px] flex-col gap-4">
                        <Panel title={t('sales.show.payments')}>
                            <dl className="flex flex-col gap-2 text-sm">
                                {sale.payments.map((payment) => (
                                    <div
                                        key={payment.id}
                                        className="flex justify-between gap-3"
                                    >
                                        <dt>{payment.label}</dt>
                                        <dd className="font-bold num">
                                            {payment.amount_label}
                                        </dd>
                                    </div>
                                ))}
                                {sale.has_change && (
                                    <div className="flex justify-between gap-3 text-muted-foreground">
                                        <dt>{t('pos.change')}</dt>
                                        <dd className="num">
                                            {sale.change_label}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </Panel>

                        {canRefund && returnable.length > 0 && (
                            <Panel
                                title={t('sales.refund.title')}
                                description={t('sales.refund.description')}
                            >
                                <Form
                                    {...refund.form(sale.id)}
                                    options={{ preserveScroll: true }}
                                    className="flex flex-col gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            {installment && (
                                                <p className="text-sm leading-relaxed text-ink-mid">
                                                    {t(
                                                        'sales.refund.installment',
                                                    )}
                                                </p>
                                            )}
                                            {!installment &&
                                                returnable.map((item, i) => (
                                                    <div
                                                        key={item.id}
                                                        className="flex items-center justify-between gap-3"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name={`items[${i}][sale_item_id]`}
                                                            value={item.id}
                                                        />
                                                        <label
                                                            htmlFor={`refund-${item.id}`}
                                                            className="min-w-0 text-sm"
                                                        >
                                                            {item.name}
                                                            <span className="block text-xs text-muted-foreground">
                                                                {t(
                                                                    'sales.refund.max',
                                                                    {
                                                                        count: item.returnable_qty,
                                                                    },
                                                                )}
                                                            </span>
                                                        </label>
                                                        <Input
                                                            id={`refund-${item.id}`}
                                                            name={`items[${i}][qty]`}
                                                            type="number"
                                                            min={0}
                                                            max={
                                                                item.returnable_qty
                                                            }
                                                            defaultValue={0}
                                                            className="w-24 text-right num"
                                                        />
                                                    </div>
                                                ))}
                                            <InputError
                                                message={errors.items}
                                            />
                                            <SelectField
                                                label={t('sales.refund.from')}
                                                name="refund_from"
                                                error={
                                                    errors.refund_from ??
                                                    errors.cash_account_id
                                                }
                                            >
                                                {refundMethods.map((method) => (
                                                    <option
                                                        key={method.value}
                                                        value={method.value}
                                                    >
                                                        {method.label}
                                                    </option>
                                                ))}
                                            </SelectField>
                                            <Field
                                                label={t('sales.refund.reason')}
                                                name="reason"
                                                error={errors.reason}
                                            />
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {t('sales.refund.submit')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </Panel>
                        )}
                    </aside>
                </div>
            </div>

            <SaleReceipt
                receipt={{
                    shop: receipt,
                    number: sale.number,
                    date: sale.date,
                    branch: sale.branch,
                    seller: sale.seller,
                    customer: sale.customer,
                    items: sale.items,
                    subtotalLabel: sale.subtotal_label,
                    discountLabel: sale.discount_label,
                    hasDiscount: sale.has_discount,
                    totalLabel: sale.total_label,
                    payments: sale.payments,
                    changeLabel: sale.change_label,
                    hasChange: sale.has_change,
                }}
            />
        </>
    );
}

SaleShow.layout = {
    breadcrumbs: [{ title: t('sales.title'), href: index() }],
};
