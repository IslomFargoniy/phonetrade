import { Package, Smartphone } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Mahsulot rasmi. Rasm bo'lmasa yoki yuklanmasa — telefon yoki quti belgisi.
 */
export function ProductImage({
    src,
    name,
    kind = 'phone',
    className,
}: {
    src: string | null | undefined;
    name: string;
    kind?: 'phone' | 'accessory';
    className?: string;
}) {
    const [failed, setFailed] = useState(false);
    const Icon = kind === 'phone' ? Smartphone : Package;

    return (
        <div
            className={cn(
                'flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-[14px] border border-white/90 bg-white',
                className,
            )}
        >
            {src && !failed ? (
                <img
                    src={src}
                    alt={name}
                    loading="lazy"
                    className="size-full object-cover"
                    onError={() => setFailed(true)}
                />
            ) : (
                <Icon
                    aria-hidden="true"
                    className="size-1/2 text-muted-foreground/60"
                />
            )}
        </div>
    );
}
