import { Form, Head } from '@inertiajs/react';
import { Field } from '@/components/field';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { index, store, update } from '@/routes/admin/plans';

type Plan = {
    id: number;
    name: string;
    price: string;
    max_branches: number | null;
    max_users: number | null;
    is_active: boolean;
    tenants: number;
};

function PlanFields({
    plan,
    errors,
    prefix,
}: {
    plan?: Plan;
    errors: Record<string, string>;
    prefix: string;
}) {
    return (
        <>
            <Field
                label={t('plans.name')}
                name="name"
                id={`${prefix}-name`}
                required
                defaultValue={plan?.name}
                error={errors.name}
            />
            <Field
                label={t('plans.price')}
                name="price"
                id={`${prefix}-price`}
                inputMode="decimal"
                required
                defaultValue={plan?.price ?? '0'}
                className="num"
                error={errors.price}
            />
            <div className="grid grid-cols-2 gap-3">
                <Field
                    label={t('plans.max_branches')}
                    name="max_branches"
                    id={`${prefix}-branches`}
                    type="number"
                    min={1}
                    placeholder="∞"
                    defaultValue={plan?.max_branches ?? ''}
                    className="num"
                    error={errors.max_branches}
                />
                <Field
                    label={t('plans.max_users')}
                    name="max_users"
                    id={`${prefix}-users`}
                    type="number"
                    min={1}
                    placeholder="∞"
                    defaultValue={plan?.max_users ?? ''}
                    className="num"
                    error={errors.max_users}
                />
            </div>
        </>
    );
}

export default function Plans({ plans }: { plans: Plan[] }) {
    return (
        <>
            <Head title={t('plans.title')} />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {plans.map((plan) => (
                    <Panel
                        key={plan.id}
                        title={plan.name}
                        description={t('plans.tenants', {
                            count: plan.tenants,
                        })}
                    >
                        <Form
                            {...update.form(plan.id)}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <PlanFields
                                        plan={plan}
                                        errors={errors}
                                        prefix={`plan-${plan.id}`}
                                    />
                                    <label className="flex items-center gap-2 text-sm font-semibold">
                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value="0"
                                        />
                                        <input
                                            type="checkbox"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={plan.is_active}
                                            className="size-4"
                                        />
                                        {t('plans.active')}
                                    </label>
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
                ))}

                <Panel
                    title={t('plans.new')}
                    description={t('plans.new.description')}
                >
                    <Form
                        {...store.form()}
                        resetOnSuccess
                        className="flex flex-col gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <PlanFields errors={errors} prefix="new-plan" />
                                <Button
                                    type="submit"
                                    className="self-start"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {t('plans.create')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>
            </div>
        </>
    );
}

Plans.layout = {
    breadcrumbs: [{ title: t('plans.title'), href: index() }],
    subtitle: t('plans.subtitle'),
};
