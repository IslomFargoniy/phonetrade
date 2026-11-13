import type { Auth, StoreContext } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** Superadmin uchun null: u hech bir do'konga tegishli emas */
            store: StoreContext | null;
            [key: string]: unknown;
        };
    }
}
