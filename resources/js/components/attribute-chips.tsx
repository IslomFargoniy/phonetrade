import { Chip } from '@/components/chip';

/** Holat kriteriyasi rang bilan ajratiladi: yangi — yashil, ishlatilgan — sariq. */
function tone(name: string, value: string) {
    if (name.toLowerCase() !== 'holat') {
        return 'neutral' as const;
    }

    return value.toLowerCase().startsWith('yangi')
        ? ('success' as const)
        : ('warning' as const);
}

export function AttributeChips({
    attributes,
}: {
    attributes: Record<string, string>;
}) {
    const entries = Object.entries(attributes);

    if (entries.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {entries.map(([name, value]) => (
                <Chip key={name} tone={tone(name, value)}>
                    {name.toLowerCase() === 'batareya'
                        ? `Batareya ${value}%`
                        : value}
                </Chip>
            ))}
        </div>
    );
}
