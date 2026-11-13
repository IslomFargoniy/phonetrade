import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Chip } from '@/components/chip';
import { Field } from '@/components/field';
import InputError from '@/components/input-error';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { index, store, update } from '@/routes/branches';

type Branch = {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    is_active: boolean;
    users_count: number;
};

function BranchRow({ branch }: { branch: Branch }) {
    const [editing, setEditing] = useState(false);

    if (!editing) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-3 glass-inner px-3.5 py-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <span className="text-[15px] font-bold">{branch.name}</span>
                    <span className="text-xs text-muted-foreground">
                        {[
                            branch.address,
                            branch.phone,
                            t('branches.staff', { count: branch.users_count }),
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <Chip tone={branch.is_active ? 'success' : 'neutral'}>
                        {branch.is_active
                            ? t('status.active')
                            : t('status.inactive')}
                    </Chip>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => setEditing(true)}
                    >
                        {t('action.edit')}
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <Form
            {...update.form(branch.id)}
            options={{ preserveScroll: true }}
            onSuccess={() => setEditing(false)}
            className="flex flex-col gap-3 glass-inner p-3.5"
        >
            {({ processing, errors }) => (
                <>
                    <Field
                        label={t('branches.field.name')}
                        name="name"
                        id={`name-${branch.id}`}
                        defaultValue={branch.name}
                        required
                        error={errors.name}
                    />
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Field
                            label={t('field.address')}
                            name="address"
                            id={`address-${branch.id}`}
                            defaultValue={branch.address ?? ''}
                            error={errors.address}
                        />
                        <Field
                            label={t('field.phone')}
                            name="phone"
                            id={`phone-${branch.id}`}
                            type="tel"
                            inputMode="tel"
                            defaultValue={branch.phone ?? ''}
                            error={errors.phone}
                        />
                    </div>
                    <fieldset className="flex flex-wrap items-center gap-4 text-sm">
                        <legend className="sr-only">
                            {t('branches.field.state')}
                        </legend>
                        <label className="flex min-h-11 items-center gap-2">
                            <input
                                type="radio"
                                name="is_active"
                                value="1"
                                defaultChecked={branch.is_active}
                                className="size-4 accent-primary"
                            />
                            {t('status.active')}
                        </label>
                        <label className="flex min-h-11 items-center gap-2">
                            <input
                                type="radio"
                                name="is_active"
                                value="0"
                                defaultChecked={!branch.is_active}
                                className="size-4 accent-primary"
                            />
                            {t('status.inactive')}
                        </label>
                    </fieldset>
                    <InputError message={errors.is_active} />
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t('action.save')}
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setEditing(false)}
                        >
                            {t('action.cancel')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function Branches({ branches }: { branches: Branch[] }) {
    return (
        <>
            <Head title={t('branches.title')} />

            <div className="flex flex-wrap items-start gap-4">
                <Panel
                    title={t('branches.title')}
                    description={t('branches.description')}
                    className="min-w-0 flex-[999_1_480px]"
                >
                    <div className="flex flex-col gap-2">
                        {branches.map((branch) => (
                            <BranchRow key={branch.id} branch={branch} />
                        ))}
                    </div>
                </Panel>

                <Panel
                    title={t('branches.new')}
                    className="min-w-0 flex-[1_1_320px]"
                >
                    <Form
                        {...store.form()}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    label={t('branches.field.name')}
                                    name="name"
                                    required
                                    error={errors.name}
                                />
                                <Field
                                    label={t('field.address')}
                                    name="address"
                                    error={errors.address}
                                />
                                <Field
                                    label={t('field.phone')}
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    placeholder="+998"
                                    error={errors.phone}
                                />
                                <Button
                                    type="submit"
                                    className="mt-1"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {t('branches.create')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>
            </div>
        </>
    );
}

Branches.layout = {
    breadcrumbs: [{ title: t('branches.title'), href: index() }],
};
