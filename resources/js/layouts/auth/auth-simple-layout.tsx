import { usePage } from '@inertiajs/react';
import { AppBackground } from '@/components/app-background';
import { AppMark } from '@/components/app-mark';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center p-4 md:p-10">
            <AppBackground />

            <div className="relative w-full max-w-sm glass p-6 sm:p-8">
                <div className="flex flex-col gap-7">
                    <div className="flex flex-col items-center gap-4">
                        <div className="flex items-center gap-3">
                            <AppMark name={name} />
                            <span className="text-[15px] font-bold">
                                {name}
                            </span>
                        </div>

                        <div className="space-y-1.5 text-center">
                            <h1 className="text-2xl font-extrabold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
