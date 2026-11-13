import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Jadval qobig'i: tor ekranda o'z ichida gorizontal aylanadi.
 */
export function DataTable({
    minWidth = 640,
    children,
}: {
    minWidth?: number;
    children: ReactNode;
}) {
    return (
        <div className="overflow-x-auto">
            <table
                className="w-full border-collapse text-sm"
                style={{ minWidth }}
            >
                {children}
            </table>
        </div>
    );
}

export function Th({
    children,
    right,
    className,
}: {
    children?: ReactNode;
    right?: boolean;
    className?: string;
}) {
    return (
        <th
            className={cn(
                'px-3 py-2.5 text-xs font-semibold text-muted-foreground',
                right ? 'text-right' : 'text-left',
                className,
            )}
        >
            {children}
        </th>
    );
}

export function Td({
    children,
    right,
    className,
    colSpan,
}: {
    children?: ReactNode;
    right?: boolean;
    className?: string;
    colSpan?: number;
}) {
    return (
        <td
            colSpan={colSpan}
            className={cn(
                'border-t border-border px-3 py-3 align-top',
                right && 'text-right',
                className,
            )}
        >
            {children}
        </td>
    );
}
