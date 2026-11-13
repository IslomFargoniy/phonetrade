import { Form, Head, Link } from '@inertiajs/react';
import { Chip } from '@/components/chip';
import type { ChipTone } from '@/components/chip';
import { Field } from '@/components/field';
import { Panel } from '@/components/panel';
import { SelectField } from '@/components/select-field';
import { StatList } from '@/components/stat-list';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { index, payments, subscription } from '@/routes/admin/tenants';

export type SubscriptionState = 'unlimited' | 'active' | 'grace' | 'expired';

type Tenant = {
    id: number;
    name: string;
    status: 'active' | 'blocked';
    created_at: string | null;
    plan_id: number | null;
    plan: string | null;
    paid_until: string | null;
    paid_until_label: string | null;
    state: SubscriptionState;
    days_left: number | null;
    branches: number;
    max_branches: number | null;
    sellers: number;
    max_users: number | null;
};

type PlanOption = {
    id: number;
    name: string;
    price: string;
    price_label: string;
};

type Payment = {
    id: number;
    date: string | null;
    plan: string;
    months: number;
    amount_label: string;
    period: string;
    note: string | null;
};

export const stateTone: Record<SubscriptionState, ChipTone> = {
    unlimited: 'neutral',
    active: 'success',
    grace: 'warning',
    expired: 'danger',
};

const usage = (used: number, limit: number | null) =>
    limit === null ? `${used} / ∞` : `${used} / ${limit}`;

export default function TenantBilling({
    tenant,
    plans,
    payments: history,
}: {
    tenant: Tenant;
    plans: PlanOption[];
    payments: Payment[];
}) {
    return (
        <>
            <Head title={tenant.name} />

            <div className="flex flex-wrap items-start gap-4">
                <div className="flex min-w-0 flex-[999_1_520px] flex-col gap-4">
                    <Panel
                        title={t('billing.subscription')}
                        action={
                            <Chip tone={stateTone[tenant.state]}>
                                {t(`billing.state.${tenant.state}`)}
                            </Chip>
                        }
                    >
                        <StatList
                            items={[
                                {
                                    label: t('billing.plan'),
                                    value: tenant.plan ?? t('billing.no_plan'),
                                },
                                {
                                    label: t('billing.paid_until'),
                                    value:
                                        tenant.paid_until_label ??
                                        t('billing.no_expiry'),
                                    strong: true,
                                },
                                {
                                    label: t('tenants.col.branches'),
                                    value: usage(
                                        tenant.branches,
                                        tenant.max_branches,
                                    ),
                                },
                                {
                                    label: t('billing.sellers'),
                                    value: usage(
                                        tenant.sellers,
                                        tenant.max_users,
                                    ),
                                },
                            ]}
                        />
                    </Panel>

                    <Panel title={t('billing.history')}>
                        {history.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('billing.history.empty')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[560px] border-collapse text-sm">
                                    <thead>
                                        <tr className="text-left text-xs font-semibold text-muted-foreground">
                                            <th className="px-3 py-2.5 font-semibold">
                                                {t('billing.col.date')}
                                            </th>
                                            <th className="px-3 py-2.5 font-semibold">
                                                {t('billing.plan')}
                                            </th>
                                            <th className="px-3 py-2.5 font-semibold">
                                                {t('billing.col.period')}
                                            </th>
                                            <th className="px-3 py-2.5 text-right font-semibold">
                                                {t('billing.amount')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {history.map((payment) => (
                                            <tr
                                                key={payment.id}
                                                className="border-t border-border"
                                            >
                                                <td className="px-3 py-3 num">
                                                    {payment.date}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="font-bold">
                                                        {payment.plan} ·{' '}
                                                        {t('billing.months', {
                                                            months: payment.months,
                                                        })}
                                                    </div>
                                                    {payment.note && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {payment.note}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-3 py-3 num">
                                                    {payment.period}
                                                </td>
                                                <td className="px-3 py-3 text-right font-bold num">
                                                    {payment.amount_label}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                </div>

                <div className="flex min-w-0 flex-[1_1_340px] flex-col gap-4">
                    <Panel
                        title={t('billing.pay.title')}
                        description={t('billing.pay.description')}
                    >
                        <Form
                            {...payments.form(tenant.id)}
                            resetOnSuccess
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <SelectField
                                        label={t('billing.plan')}
                                        name="plan_id"
                                        id="pay-plan"
                                        required
                                        defaultValue={tenant.plan_id ?? ''}
                                        error={errors.plan_id}
                                    >
                                        <option value="" disabled>
                                            {t('billing.choose_plan')}
                                        </option>
                                        {plans.map((plan) => (
                                            <option
                                                key={plan.id}
                                                value={plan.id}
                                            >
                                                {plan.name} — {plan.price_label}
                                            </option>
                                        ))}
                                    </SelectField>
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field
                                            label={t('billing.months_count')}
                                            name="months"
                                            type="number"
                                            min={1}
                                            max={36}
                                            defaultValue={1}
                                            required
                                            className="num"
                                            error={errors.months}
                                        />
                                        <Field
                                            label={t('billing.amount')}
                                            name="amount"
                                            inputMode="decimal"
                                            required
                                            className="num"
                                            error={errors.amount}
                                        />
                                    </div>
                                    <Field
                                        label={t('billing.note')}
                                        name="note"
                                        error={errors.note}
                                    />
                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('billing.pay.submit')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Panel>

                    <Panel
                        title={t('billing.manual.title')}
                        description={t('billing.manual.description')}
                    >
                        <Form
                            {...subscription.form(tenant.id)}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <SelectField
                                        label={t('billing.plan')}
                                        name="plan_id"
                                        id="manual-plan"
                                        defaultValue={tenant.plan_id ?? ''}
                                        error={errors.plan_id}
                                    >
                                        <option value="">
                                            {t('billing.no_plan')}
                                        </option>
                                        {plans.map((plan) => (
                                            <option
                                                key={plan.id}
                                                value={plan.id}
                                            >
                                                {plan.name}
                                            </option>
                                        ))}
                                    </SelectField>
                                    <Field
                                        label={t('billing.paid_until')}
                                        name="paid_until"
                                        type="date"
                                        defaultValue={tenant.paid_until ?? ''}
                                        hint={t('billing.manual.hint')}
                                        error={errors.paid_until}
                                    />
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        className="self-start"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('action.save')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Panel>

                    <Button asChild variant="outline" className="self-start">
                        <Link href={index()}>{t('billing.back')}</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

TenantBilling.layout = {
    breadcrumbs: [{ title: t('billing.title'), href: index() }],
};
