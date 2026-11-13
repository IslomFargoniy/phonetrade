import { cn } from '@/lib/utils';

/**
 * Do'kon belgisi: nomning birinchi harfi qora kvadratda.
 */
export function AppMark({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'flex size-[38px] shrink-0 items-center justify-center rounded-xl bg-ink text-base font-extrabold text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.25)]',
                className,
            )}
        >
            {name.trim().charAt(0).toUpperCase() || 'P'}
        </div>
    );
}
