import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Chip } from '@/components/chip';
import { Field } from '@/components/field';
import InputError from '@/components/input-error';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { index, store, update } from '@/routes/staff';
import type { BranchOption } from '@/types';

type Member = {
    id: number;
    name: string;
    phone: string;
    role: 'owner' | 'seller';
    role_label: string;
    is_active: boolean;
    branch_ids: number[];
    branch_names: string[];
};

function BranchChecks({
    branches,
    selected,
    idPrefix,
    error,
}: {
    branches: BranchOption[];
    selected: number[];
    idPrefix: string;
    error?: string;
}) {
    return (
        <fieldset className="flex flex-col gap-1.5">
            <legend className="mb-1.5 text-xs font-semibold text-muted-foreground">
                {t('staff.field.branches')}
            </legend>
            <div className="flex flex-wrap gap-x-4 gap-y-1">
                {branches.map((branch) => (
                    <label
                        key={branch.id}
                        htmlFor={`${idPrefix}-branch-${branch.id}`}
                        className="flex min-h-11 items-center gap-2 text-sm"
                    >
                        <input
                            id={`${idPrefix}-branch-${branch.id}`}
                            type="checkbox"
                            name="branch_ids[]"
                            value={branch.id}
                            defaultChecked={selected.includes(branch.id)}
                            className="size-4 accent-primary"
                        />
                        {branch.name}
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}

function MemberRow({
    member,
    branches,
}: {
    member: Member;
    branches: BranchOption[];
}) {
    const [editing, setEditing] = useState(false);

    if (!editing) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-3 glass-inner px-3.5 py-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <span className="text-[15px] font-bold">{member.name}</span>
                    <span className="text-xs num text-muted-foreground">
                        {[
                            member.phone,
                            member.role === 'owner'
                                ? t('staff.all_branches')
                                : member.branch_names.join(', '),
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    {!member.is_active && (
                        <Chip tone="danger">{t('status.inactive')}</Chip>
                    )}
                    <Chip tone={member.role === 'owner' ? 'info' : 'neutral'}>
                        {member.role_label}
                    </Chip>
                    {member.role === 'seller' && (
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => setEditing(true)}
                        >
                            {t('action.edit')}
                        </Button>
                    )}
                </div>
            </div>
        );
    }

    return (
        <Form
            {...update.form(member.id)}
            options={{ preserveScroll: true }}
            onSuccess={() => setEditing(false)}
            className="flex flex-col gap-3 glass-inner p-3.5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Field
                            label={t('field.name')}
                            name="name"
                            id={`name-${member.id}`}
                            defaultValue={member.name}
                            required
                            error={errors.name}
                        />
                        <Field
                            label={t('field.phone')}
                            name="phone"
                            id={`phone-${member.id}`}
                            type="tel"
                            inputMode="tel"
                            defaultValue={member.phone}
                            required
                            error={errors.phone}
                        />
                    </div>
                    <Field
                        label={t('staff.field.new_password')}
                        name="password"
                        id={`password-${member.id}`}
                        type="text"
                        autoComplete="off"
                        hint={t('staff.field.new_password.hint')}
                        error={errors.password}
                    />
                    <BranchChecks
                        branches={branches}
                        selected={member.branch_ids}
                        idPrefix={`member-${member.id}`}
                        error={errors.branch_ids}
                    />
                    <fieldset className="flex flex-wrap items-center gap-4 text-sm">
                        <legend className="sr-only">
                            {t('staff.field.state')}
                        </legend>
                        <label className="flex min-h-11 items-center gap-2">
                            <input
                                type="radio"
                                name="is_active"
                                value="1"
                                defaultChecked={member.is_active}
                                className="size-4 accent-primary"
                            />
                            {t('status.active')}
                        </label>
                        <label className="flex min-h-11 items-center gap-2">
                            <input
                                type="radio"
                                name="is_active"
                                value="0"
                                defaultChecked={!member.is_active}
                                className="size-4 accent-primary"
                            />
                            {t('staff.state.off')}
                        </label>
                    </fieldset>
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

export default function Staff({
    staff,
    branches,
}: {
    staff: Member[];
    branches: BranchOption[];
}) {
    return (
        <>
            <Head title={t('staff.title')} />

            <div className="flex flex-wrap items-start gap-4">
                <Panel
                    title={t('staff.title')}
                    description={t('staff.description')}
                    className="min-w-0 flex-[999_1_480px]"
                >
                    <div className="flex flex-col gap-2">
                        {staff.map((member) => (
                            <MemberRow
                                key={member.id}
                                member={member}
                                branches={branches}
                            />
                        ))}
                    </div>
                </Panel>

                <Panel
                    title={t('staff.new')}
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
                                    label={t('field.name')}
                                    name="name"
                                    required
                                    autoComplete="off"
                                    error={errors.name}
                                />
                                <Field
                                    label={t('field.phone')}
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    required
                                    autoComplete="off"
                                    placeholder="+998 90 123 45 67"
                                    error={errors.phone}
                                />
                                <Field
                                    label={t('field.password')}
                                    name="password"
                                    type="text"
                                    required
                                    autoComplete="off"
                                    hint={t('staff.field.password.hint')}
                                    error={errors.password}
                                />
                                <BranchChecks
                                    branches={branches}
                                    selected={
                                        branches.length === 1
                                            ? [branches[0].id]
                                            : []
                                    }
                                    idPrefix="new"
                                    error={errors.branch_ids}
                                />
                                <Button
                                    type="submit"
                                    className="mt-1"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {t('staff.create')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>
            </div>
        </>
    );
}

Staff.layout = {
    breadcrumbs: [{ title: t('staff.title'), href: index() }],
};
