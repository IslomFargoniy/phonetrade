import { Form, Head, router } from '@inertiajs/react';
import { Chip } from '@/components/chip';
import { Field } from '@/components/field';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { index, status, store } from '@/routes/admin/tenants';

type Tenant = {
    id: number;
    name: string;
    status: 'active' | 'blocked';
    branches_count: number;
    users_count: number;
    owner_name: string | null;
    owner_phone: string | null;
    created_at: string | null;
};

export default function Tenants({ tenants }: { tenants: Tenant[] }) {
    const setStatus = (tenant: Tenant) =>
        router.patch(
            status(tenant.id).url,
            { status: tenant.status === 'active' ? 'blocked' : 'active' },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('tenants.title')} />

            <div className="flex flex-wrap items-start gap-4">
                <Panel
                    title={t('tenants.list')}
                    className="min-w-0 flex-[999_1_520px]"
                >
                    {tenants.length === 0 ? (
                        <p className="px-3.5 pb-3 text-sm text-ink-mid">
                            {t('tenants.empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] border-collapse text-sm">
                                <thead>
                                    <tr className="text-left text-xs font-semibold text-muted-foreground">
                                        <th className="px-3.5 py-2.5 font-semibold">
                                            {t('tenants.col.store')}
                                        </th>
                                        <th className="px-3.5 py-2.5 font-semibold">
                                            {t('tenants.col.owner')}
                                        </th>
                                        <th className="px-3.5 py-2.5 text-right font-semibold">
                                            {t('tenants.col.branches')}
                                        </th>
                                        <th className="px-3.5 py-2.5 text-right font-semibold">
                                            {t('tenants.col.users')}
                                        </th>
                                        <th className="px-3.5 py-2.5 font-semibold">
                                            {t('tenants.col.status')}
                                        </th>
                                        <th className="px-3.5 py-2.5" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {tenants.map((tenant) => (
                                        <tr
                                            key={tenant.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-3.5 py-3">
                                                <div className="font-bold">
                                                    {tenant.name}
                                                </div>
                                                <div className="text-xs num text-muted-foreground">
                                                    {tenant.created_at}
                                                </div>
                                            </td>
                                            <td className="px-3.5 py-3">
                                                <div>{tenant.owner_name}</div>
                                                <div className="text-xs num text-muted-foreground">
                                                    {tenant.owner_phone}
                                                </div>
                                            </td>
                                            <td className="px-3.5 py-3 text-right num">
                                                {tenant.branches_count}
                                            </td>
                                            <td className="px-3.5 py-3 text-right num">
                                                {tenant.users_count}
                                            </td>
                                            <td className="px-3.5 py-3">
                                                <Chip
                                                    tone={
                                                        tenant.status ===
                                                        'active'
                                                            ? 'success'
                                                            : 'danger'
                                                    }
                                                >
                                                    {tenant.status === 'active'
                                                        ? t('status.active')
                                                        : t('status.blocked')}
                                                </Chip>
                                            </td>
                                            <td className="px-3.5 py-3 text-right">
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() =>
                                                        setStatus(tenant)
                                                    }
                                                >
                                                    {tenant.status === 'active'
                                                        ? t('tenants.block')
                                                        : t('tenants.unblock')}
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>

                <Panel
                    title={t('tenants.new')}
                    description={t('tenants.new.description')}
                    className="min-w-0 flex-[1_1_340px]"
                >
                    <Form
                        {...store.form()}
                        resetOnSuccess
                        className="flex flex-col gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    label={t('tenants.field.name')}
                                    name="name"
                                    required
                                    error={errors.name}
                                />
                                <Field
                                    label={t('tenants.field.branch')}
                                    name="branch_name"
                                    required
                                    error={errors.branch_name}
                                />
                                <Field
                                    label={t('tenants.field.owner')}
                                    name="owner_name"
                                    required
                                    autoComplete="off"
                                    error={errors.owner_name}
                                />
                                <Field
                                    label={t('tenants.field.phone')}
                                    name="owner_phone"
                                    type="tel"
                                    inputMode="tel"
                                    required
                                    autoComplete="off"
                                    placeholder="+998 90 123 45 67"
                                    error={errors.owner_phone}
                                />
                                <Field
                                    label={t('field.password')}
                                    name="owner_password"
                                    type="text"
                                    required
                                    autoComplete="off"
                                    hint={t('tenants.field.password.hint')}
                                    error={errors.owner_password}
                                />
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="mt-1"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {t('tenants.create')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>
            </div>
        </>
    );
}

Tenants.layout = {
    breadcrumbs: [{ title: t('tenants.title'), href: index() }],
    subtitle: t('tenants.subtitle'),
};
