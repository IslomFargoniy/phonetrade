import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = ComponentProps<'select'> & {
    label: string;
    name: string;
    error?: string;
    wrapperClassName?: string;
    children: ReactNode;
};

/**
 * Yorliqli oddiy `<select>` — Field bilan bir xil ko'rinishda.
 */
export function SelectField({
    label,
    name,
    error,
    id,
    className,
    wrapperClassName,
    children,
    ...props
}: Props) {
    const fieldId = id ?? name;

    return (
        <div className={cn('grid min-w-0 gap-1.5', wrapperClassName)}>
            <Label
                htmlFor={fieldId}
                className="text-xs font-semibold text-muted-foreground"
            >
                {label}
            </Label>
            <select
                id={fieldId}
                name={name}
                aria-invalid={error ? true : undefined}
                className={cn(
                    'min-h-11 w-full min-w-0 rounded-[14px] border border-white/90 bg-white/72 px-3 text-[15px] text-ink shadow-[inset_0_1px_2px_rgb(28_35_64/0.07)] outline-none focus-visible:ring-[3px] focus-visible:ring-ring',
                    className,
                )}
                {...props}
            >
                {children}
            </select>
            <InputError message={error} />
        </div>
    );
}
