import { usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import { AppBackground } from '@/components/app-background';
import { AppBrand } from '@/components/app-brand';
import { AppNav } from '@/components/app-nav';
import { BranchSwitcher } from '@/components/branch-switcher';
import { RateBadge } from '@/components/rate-badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserMenu } from '@/components/user-menu';
import { t } from '@/lib/i18n';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    subtitle,
    children,
}: AppLayoutProps) {
    const { name } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const title = breadcrumbs.at(-1)?.title ?? name;

    return (
        <div className="relative min-h-svh">
            <AppBackground />

            <div className="relative flex min-h-svh flex-col gap-4 p-4 lg:flex-row lg:items-start">
                <aside className="hidden w-[232px] shrink-0 flex-col glass p-3 lg:sticky lg:top-4 lg:flex">
                    <AppBrand />
                    <AppNav />
                    <RateBadge />
                </aside>

                <div className="flex min-w-0 flex-1 flex-col gap-4">
                    <header className="flex flex-wrap items-center justify-between gap-3 glass py-3.5 pr-4 pl-4 lg:pl-6">
                        <div className="flex min-w-0 items-center gap-3">
                            <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                                <SheetTrigger asChild>
                                    <Button
                                        variant="secondary"
                                        size="icon"
                                        className="lg:hidden"
                                        aria-label={t('nav.open')}
                                    >
                                        <Menu className="size-5" />
                                    </Button>
                                </SheetTrigger>
                                <SheetContent
                                    side="left"
                                    className="w-[280px] border-white/80 bg-white/85 p-3 backdrop-blur-2xl"
                                >
                                    <SheetTitle className="sr-only">
                                        {t('nav.title')}
                                    </SheetTitle>
                                    <SheetDescription className="sr-only">
                                        {t('nav.title')}
                                    </SheetDescription>
                                    <AppBrand />
                                    <AppNav
                                        onNavigate={() => setMenuOpen(false)}
                                    />
                                    <RateBadge />
                                </SheetContent>
                            </Sheet>

                            <div className="flex min-w-0 flex-col gap-0.5">
                                <h1 className="truncate text-[26px] leading-tight font-extrabold tracking-tight">
                                    {title}
                                </h1>
                                {subtitle && (
                                    <p className="text-[13px] text-muted-foreground">
                                        {subtitle}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <BranchSwitcher />
                            <UserMenu />
                        </div>
                    </header>

                    <main className="flex min-w-0 flex-col gap-4">
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}
