import { cn } from '@/lib/utils';

type Option<T extends string> = { value: T; label: string };

/**
 * Bir nechta variantdan bittasini tanlash (iOS segmented control uslubida).
 */
export function Segmented<T extends string>({
    options,
    value,
    onChange,
    label,
    className,
}: {
    options: readonly Option<T>[];
    value: T;
    onChange: (value: T) => void;
    label: string;
    className?: string;
}) {
    return (
        <div
            role="group"
            aria-label={label}
            className={cn(
                'flex flex-wrap gap-1 self-start rounded-[22px] bg-muted p-1',
                className,
            )}
        >
            {options.map((option) => {
                const active = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={active}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'min-h-[38px] cursor-pointer rounded-full px-4 text-sm',
                            active
                                ? 'bg-white font-bold text-ink shadow-[0_2px_8px_rgb(28_35_64/0.14),inset_0_1px_0_#fff]'
                                : 'font-semibold text-ink-mid',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
