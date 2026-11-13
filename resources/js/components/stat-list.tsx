/**
 * Yorliq — qiymat juftliklari, tor ustunda ham sig'adigan ko'rinishda.
 */
export function StatList({
    items,
}: {
    items: { label: string; value: string; strong?: boolean }[];
}) {
    return (
        <dl className="flex flex-col glass-inner px-3.5 py-1 text-sm">
            {items.map((item, i) => (
                <div
                    key={item.label}
                    className={
                        i === 0
                            ? 'flex items-baseline justify-between gap-3 py-2'
                            : 'flex items-baseline justify-between gap-3 border-t border-border py-2'
                    }
                >
                    <dt className="text-xs font-semibold text-muted-foreground">
                        {item.label}
                    </dt>
                    <dd
                        className={
                            item.strong
                                ? 'text-base font-extrabold num text-primary-strong'
                                : 'text-[15px] font-bold num'
                        }
                    >
                        {item.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
