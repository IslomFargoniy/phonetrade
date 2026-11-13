import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, Store } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import { select } from '@/routes/branches';

/**
 * Ishlayotgan filialni almashtirish. Bitta filial bo'lsa ko'rsatilmaydi.
 */
export function BranchSwitcher() {
    const { store } = usePage().props;

    if (!store || store.branches.length < 2) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="secondary" aria-label={t('branch.switch')}>
                    <Store className="size-4" />
                    <span className="max-w-40 truncate">
                        {store.branch?.name ?? t('branch.none')}
                    </span>
                    <ChevronDown className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-56 rounded-2xl">
                {store.branches.map((branch) => (
                    <DropdownMenuItem
                        key={branch.id}
                        className="cursor-pointer"
                        onSelect={() =>
                            router.post(
                                select(branch.id).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Check
                            className={
                                branch.id === store.branch?.id
                                    ? 'mr-2 size-4'
                                    : 'mr-2 size-4 opacity-0'
                            }
                        />
                        {branch.name}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
