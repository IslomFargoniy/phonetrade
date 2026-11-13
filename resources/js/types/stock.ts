/** Bitta telefon. `cost*` maydonlari faqat do'kon egasiga keladi. */
export type StockUnitRow = {
    id: number;
    product: string;
    branch_id: number;
    branch: string;
    imei1: string;
    imei2: string | null;
    barcode: string;
    attributes: Record<string, string>;
    sale_price: string;
    sale_currency: 'UZS' | 'USD';
    sale_price_label: string;
    max_discount_pct: string;
    warranty_months: number;
    status: string;
    status_label: string;
    received_at: string | null;
    cost?: string;
    cost_currency?: 'UZS' | 'USD';
    cost_label?: string;
    cost_rate?: string | null;
};

/** Miqdor bilan yuritiladigan tovar (aksesuar) va uning qoldig'i. */
export type StockVariantRow = {
    id: number;
    product: string;
    barcode: string;
    attributes: Record<string, string>;
    sale_price: string;
    sale_currency: 'UZS' | 'USD';
    sale_price_label: string;
    max_discount_pct: string;
    qty: number;
};
