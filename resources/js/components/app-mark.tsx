import { cn } from '@/lib/utils';

/**
 * Tizim logotipi: ikki telefon — eskisi orqada, yangisi oldinda.
 * Manba fayl — public/logo.svg; favicon va mobil ilova ikonkalari ham shundan olingan.
 */
export function AppMark({ className }: { className?: string }) {
    return (
        <img
            src="/logo.svg"
            alt=""
            aria-hidden="true"
            width={38}
            height={38}
            className={cn(
                'size-[38px] shrink-0 rounded-xl shadow-[0_2px_8px_rgb(0_88_194/0.35)]',
                className,
            )}
        />
    );
}
