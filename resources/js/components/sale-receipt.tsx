export type ReceiptData = {
    shop: { name: string; phone: string | null; footer: string | null };
    number: number;
    date: string | null;
    branch: string;
    seller: string | null;
    customer: string | null;
    items: {
        id: number;
        name: string;
        qty: number;
        imei: string | null;
        final_label: string;
        discount_label: string;
        has_discount: boolean;
        warranty_until: string | null;
    }[];
    subtotalLabel: string;
    discountLabel: string;
    hasDiscount: boolean;
    totalLabel: string;
    payments: { id: number; label: string; amount_label: string }[];
    changeLabel: string;
    hasChange: boolean;
};

/**
 * Termal chek (80 mm). Faqat chop etishda ko'rinadi. 58 mm printerda brauzer
 * o'zi qog'oz kengligiga moslaydi.
 */
export function SaleReceipt({ receipt }: { receipt: ReceiptData }) {
    return (
        <div className="hidden w-[72mm] bg-white p-1 font-mono text-[9pt] leading-snug text-black print:block">
            <div className="text-center">
                <div className="text-[12pt] font-bold">{receipt.shop.name}</div>
                <div>{receipt.branch}</div>
                {receipt.shop.phone && <div>{receipt.shop.phone}</div>}
            </div>

            <div className="my-1.5 border-t border-dashed border-black" />

            <div className="flex justify-between">
                <span>Chek № {receipt.number}</span>
                <span>{receipt.date}</span>
            </div>
            {receipt.seller && <div>Sotuvchi: {receipt.seller}</div>}
            {receipt.customer && <div>Mijoz: {receipt.customer}</div>}

            <div className="my-1.5 border-t border-dashed border-black" />

            {receipt.items.map((item) => (
                <div key={item.id} className="mb-1">
                    <div className="flex justify-between gap-2">
                        <span className="font-bold">
                            {item.name}
                            {item.qty > 1 && ` × ${item.qty}`}
                        </span>
                        <span className="whitespace-nowrap">
                            {item.final_label}
                        </span>
                    </div>
                    {item.imei && <div>IMEI {item.imei}</div>}
                    {item.has_discount && (
                        <div>Chegirma: {item.discount_label}</div>
                    )}
                    {item.warranty_until && (
                        <div>Kafolat: {item.warranty_until} gacha</div>
                    )}
                </div>
            ))}

            <div className="my-1.5 border-t border-dashed border-black" />

            {receipt.hasDiscount && (
                <>
                    <div className="flex justify-between">
                        <span>Oraliq summa</span>
                        <span>{receipt.subtotalLabel}</span>
                    </div>
                    <div className="flex justify-between">
                        <span>Chegirma</span>
                        <span>−{receipt.discountLabel}</span>
                    </div>
                </>
            )}
            <div className="flex justify-between text-[12pt] font-bold">
                <span>JAMI</span>
                <span>{receipt.totalLabel}</span>
            </div>

            <div className="my-1.5 border-t border-dashed border-black" />

            {receipt.payments.map((payment) => (
                <div key={payment.id} className="flex justify-between">
                    <span>{payment.label}</span>
                    <span>{payment.amount_label}</span>
                </div>
            ))}
            {receipt.hasChange && (
                <div className="flex justify-between">
                    <span>Qaytim</span>
                    <span>{receipt.changeLabel}</span>
                </div>
            )}

            {receipt.shop.footer && (
                <>
                    <div className="my-1.5 border-t border-dashed border-black" />
                    <div className="text-center">{receipt.shop.footer}</div>
                </>
            )}
        </div>
    );
}
