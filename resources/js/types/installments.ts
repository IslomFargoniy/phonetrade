export type PlanState =
    | 'late'
    | 'today'
    | 'upcoming'
    | 'active'
    | 'closed'
    | 'cancelled';

export type PlanRow = {
    id: number;
    sale_id: number;
    sale_number: number;
    customer_id: number;
    customer: string;
    customer_phone: string;
    customer_phone_raw: string;
    product: string;
    months: number;
    mode: 'A' | 'B';
    currency: 'UZS' | 'USD';
    status: 'active' | 'closed' | 'cancelled';
    state: PlanState;
    state_label: string;
    next_date: string | null;
    next_amount: string | null;
    next_amount_label: string | null;
    paid_label: string;
    remaining: string;
    remaining_label: string;
    total_label: string;
    down_payment_label: string | null;
    markup_pct: string;
    created_at: string | null;
    schedule: {
        no: number;
        date: string;
        amount_label: string;
        paid_label: string;
        state: 'paid' | 'late' | 'partial' | 'pending';
    }[];
    payments: {
        id: number;
        date: string;
        amount_label: string;
        is_initial: boolean;
    }[];
};
