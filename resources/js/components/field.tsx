import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = ComponentProps<typeof Input> & {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    wrapperClassName?: string;
};

/**
 * Yorliq + maydon + xato matni. `id` berilmasa `name` ishlatiladi.
 */
export function Field({
    label,
    name,
    error,
    hint,
    id,
    wrapperClassName,
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
            <Input
                id={fieldId}
                name={name}
                aria-invalid={error ? true : undefined}
                {...props}
            />
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}
