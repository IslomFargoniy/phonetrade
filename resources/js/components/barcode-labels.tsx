import JsBarcode from 'jsbarcode';
import { useEffect, useRef } from 'react';

export type LabelData = {
    barcode: string;
    title: string;
    subtitle?: string;
    price?: string;
};

function Label({ label }: { label: LabelData }) {
    const ref = useRef<SVGSVGElement>(null);

    useEffect(() => {
        if (ref.current) {
            JsBarcode(ref.current, label.barcode, {
                format: 'CODE128',
                width: 1.6,
                height: 42,
                fontSize: 12,
                margin: 0,
            });
        }
    }, [label.barcode]);

    return (
        <div className="flex h-[30mm] w-[50mm] break-inside-avoid flex-col items-center justify-center gap-0.5 overflow-hidden p-1 text-center text-black">
            <span className="w-full truncate text-[9pt] leading-tight font-bold">
                {label.title}
            </span>
            {label.subtitle && (
                <span className="w-full truncate text-[7pt] leading-tight">
                    {label.subtitle}
                </span>
            )}
            <svg ref={ref} role="img" aria-label={label.barcode} />
            {label.price && (
                <span className="text-[9pt] leading-tight font-bold">
                    {label.price}
                </span>
            )}
        </div>
    );
}

/**
 * Etiketkalar faqat chop etishda ko'rinadi (50×30 mm). Ekranda yashirin turadi.
 */
export function BarcodeLabels({ labels }: { labels: LabelData[] }) {
    return (
        <div className="hidden flex-wrap bg-white print:flex">
            {labels.map((label) => (
                <Label key={label.barcode} label={label} />
            ))}
        </div>
    );
}
