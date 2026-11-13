import { usePage } from '@inertiajs/react';
import { AppMark } from '@/components/app-mark';

/**
 * Yon menyu tepasi: do'kon nomi va joriy filial. Superadminda platforma nomi.
 */
export function AppBrand() {
    const { name, store } = usePage().props;
    const title = store?.name ?? name;

    return (
        <div className="flex items-center gap-3 px-2.5 pt-1.5 pb-4">
            <AppMark name={title} />
            <div className="flex min-w-0 flex-col">
                <span className="truncate text-[15px] font-bold">{title}</span>
                {store?.branch && (
                    <span className="truncate text-xs text-muted-foreground">
                        {store.branch.name}
                    </span>
                )}
            </div>
        </div>
    );
}
