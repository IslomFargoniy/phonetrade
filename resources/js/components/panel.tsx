import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Sarlavhali shisha panel — sahifadagi asosiy blok.
 */
export function Panel({
    title,
    description,
    action,
    className,
    children,
}: {
    title?: string;
    description?: string;
    action?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section className={cn('flex flex-col gap-4 glass p-5', className)}>
            {(title || action) && (
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="flex flex-col gap-0.5">
                        {title && (
                            <h2 className="text-[17px] font-bold">{title}</h2>
                        )}
                        {description && (
                            <p className="text-[13px] leading-relaxed text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}
