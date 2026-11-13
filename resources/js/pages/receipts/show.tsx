import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { AttributeChips } from '@/components/attribute-chips';
import { BarcodeLabels } from '@/components/barcode-labels';
import type { LabelData } from '@/components/barcode-labels';
import { DataTable, Td, Th } from '@/components/data-table';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { ProductImage } from '@/components/product-image';
import { UnitPhotos } from '@/components/unit-photos';
import { t } from '@/lib/i18n';
import { create } from '@/routes/receipts';
import type { StockUnitRow } from '@/types';

type Item = {
    id: number;
    product: string;
    image_url: string | null;
    qty: number;
    attributes: Record<string, string>;
    unit_cost_label: string;
    sale_price_label: string;
    units: StockUnitRow[];
};

type Props = {
    purchase: {
        id: number;
        type_label: string;
        date: string;
        branch: string;
        counterparty: string | null;
        total_label: string;
        rate: string | null;
        items: Item[];
    };
};

export default function ReceiptShow({ purchase }: Props) {
    const labels: LabelData[] = purchase.items.flatMap((item) =>
        item.units.map((unit) => ({
            barcode: unit.barcode,
            title: unit.product,
            subtitle: Object.values(unit.attributes).join(' · '),
            price: unit.sale_price_label,
        })),
    );

    return (
        <>
            <Head title={t('receipt.show.title', { id: purchase.id })} />

            <div className="flex flex-col gap-4 print:hidden">
                <Panel
                    title={t('receipt.show.title', { id: purchase.id })}
                    description={[
                        purchase.type_label,
                        purchase.counterparty,
                        purchase.branch,
                        purchase.date,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    action={
                        <div className="flex flex-wrap gap-2">
                            {labels.length > 0 && (
                                <Button
                                    variant="secondary"
                                    onClick={() => window.print()}
                                >
                                    <Printer />
                                    {t('receipt.show.print', {
                                        count: labels.length,
                                    })}
                                </Button>
                            )}
                            <Button asChild>
                                <Link href={create()}>
                                    {t('receipt.show.new')}
                                </Link>
                            </Button>
                        </div>
                    }
                >
                    <span className="text-2xl font-extrabold num tracking-tight">
                        {purchase.total_label}
                    </span>
                </Panel>

                {purchase.items.map((item) => (
                    <Panel
                        key={item.id}
                        title={`${item.product} × ${item.qty}`}
                        description={t('receipt.show.prices', {
                            cost: item.unit_cost_label,
                            price: item.sale_price_label,
                        })}
                    >
                        <ProductImage
                            src={item.image_url}
                            name={item.product}
                            className="size-16"
                        />
                        <AttributeChips attributes={item.attributes} />
                        {item.units.length > 0 && (
                            <p className="text-[13px] text-muted-foreground">
                                {t('photos.receipt_hint')}
                            </p>
                        )}
                        {item.units.length > 0 && (
                            <DataTable minWidth={640}>
                                <thead>
                                    <tr>
                                        <Th>№</Th>
                                        <Th>IMEI 1</Th>
                                        <Th>IMEI 2</Th>
                                        <Th>{t('stock.col.barcode')}</Th>
                                        <Th>{t('photos.title')}</Th>
                                    </tr>
                                </thead>
                                <tbody className="num">
                                    {item.units.map((unit, i) => (
                                        <tr key={unit.id}>
                                            <Td className="text-muted-foreground">
                                                {i + 1}
                                            </Td>
                                            <Td>{unit.imei1}</Td>
                                            <Td>{unit.imei2}</Td>
                                            <Td className="font-semibold text-info">
                                                {unit.barcode}
                                            </Td>
                                            <Td>
                                                <UnitPhotos
                                                    unitId={unit.id}
                                                    photos={unit.photos}
                                                    size="sm"
                                                />
                                            </Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </DataTable>
                        )}
                    </Panel>
                ))}
            </div>

            <BarcodeLabels labels={labels} />
        </>
    );
}

ReceiptShow.layout = {
    breadcrumbs: [{ title: t('receipt.title'), href: create() }],
};
