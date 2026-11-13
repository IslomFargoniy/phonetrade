import type { PropsWithChildren } from 'react';
import { AppBackground } from '@/components/app-background';

/**
 * Kirishsiz ochiladigan sahifalar (mijozlar uchun vitrina): menyusiz, faqat fon.
 */
export default function PublicLayout({ children }: PropsWithChildren) {
    return (
        <div className="relative min-h-svh">
            <AppBackground />
            <div className="relative mx-auto w-full max-w-3xl p-4">
                {children}
            </div>
        </div>
    );
}
