export type Role = 'superadmin' | 'owner' | 'seller';

export type User = {
    id: number;
    name: string;
    phone: string;
    role: Role;
    role_label: string;
    avatar?: string;
    two_factor_enabled?: boolean;
};

export type Auth = {
    user: User;
};

export type BranchOption = {
    id: number;
    name: string;
};

/** Do'kon ichidagi har sahifaga beriladigan umumiy ma'lumot */
export type StoreContext = {
    name: string;
    branch: BranchOption | null;
    branches: BranchOption[];
    rate: { usd_uzs: string; date: string; is_today: boolean } | null;
    subscription: {
        /** unlimited — muddat qo'yilmagan; grace — imtiyozli davr; expired — faqat o'qish */
        state: 'unlimited' | 'active' | 'grace' | 'expired';
        plan: string | null;
        paid_until: string | null;
        days_left: number | null;
        read_only_from: string | null;
    };
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
