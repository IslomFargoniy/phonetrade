import { usePage } from '@inertiajs/react';
import { AppMark } from '@/components/app-mark';

export function AppBrand() {
    const { name } = usePage().props;

    return (
        <div className="flex items-center gap-3 px-2.5 pt-1.5 pb-4">
            <AppMark name={name} />
            <span className="truncate text-[15px] font-bold">{name}</span>
        </div>
    );
}
