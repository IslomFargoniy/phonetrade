import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const tones = {
    neutral: 'border-white/95 bg-white/80 text-ink-soft',
    success: 'border-transparent bg-success-bg text-success',
    warning: 'border-transparent bg-warning-bg text-warning',
    danger: 'border-transparent bg-danger-bg text-danger',
    info: 'border-transparent bg-info-bg text-info',
} as const;

export type ChipTone = keyof typeof tones;

export function Chip({
    tone = 'neutral',
    className,
    children,
}: {
    tone?: ChipTone;
    className?: string;
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-block rounded-full border px-2.5 py-[3px] text-xs font-semibold whitespace-nowrap',
                tones[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}
