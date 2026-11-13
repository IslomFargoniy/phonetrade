import { Form, Head, router } from '@inertiajs/react';
import { DataTable, Td, Th } from '@/components/data-table';
import { Field } from '@/components/field';
import { Panel } from '@/components/panel';
import { Segmented } from '@/components/segmented';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { index, sweep } from '@/routes/cash';
import { store as storeCategory } from '@/routes/cash/categories';
import { store as storeExpense } from '@/routes/cash/expenses';

type Props = {
    scope: 'branch' | 'admin';
    canManage: boolean;
    branchName: string;
    accounts: {
        id: number;
        label: string;
        uzs_label: string;
        usd_label: string;
    }[];
    expenseAccounts: { value: string; label: string }[];
    categories: { id: number; name: string }[];
    movements: {
        id: number;
        time: string | null;
        kind: string;
        note: string | null;
        account: string;
        in: string | null;
        out: string | null;
    }[];
};

export default function Cash({
    scope,
    canManage,
    branchName,
    accounts,
    expenseAccounts,
    categories,
    movements,
}: Props) {
    return (
        <>
            <Head title={t('cash.title')} />

            {canManage && (
                <Segmented
                    label={t('cash.scope')}
                    options={[
                        { value: 'branch', label: branchName },
                        { value: 'admin', label: t('cash.scope.admin') },
                    ]}
                    value={scope}
                    onChange={(next) =>
                        router.get(
                            index().url,
                            { scope: next },
                            { preserveScroll: true },
                        )
                    }
                />
            )}

            <div className="grid grid-cols-[repeat(auto-fit,minmax(210px,1fr))] gap-3">
                {accounts.map((account) => (
                    <section
                        key={account.id}
                        className="flex flex-col gap-1.5 glass rounded-3xl p-[18px]"
                    >
                        <span className="text-xs font-semibold text-muted-foreground">
                            {account.label}
                        </span>
                        <span className="text-2xl font-extrabold num tracking-tight">
                            {account.uzs_label}
                        </span>
                        <span className="text-[15px] font-bold num text-ink-mid">
                            {account.usd_label}
                        </span>
                    </section>
                ))}
            </div>

            <div className="flex flex-wrap items-start gap-4">
                <Panel
                    title={t('cash.journal')}
                    description={t('cash.journal.description')}
                    className="min-w-0 flex-[999_1_560px]"
                >
                    {movements.length === 0 ? (
                        <p className="text-sm text-ink-mid">
                            {t('cash.journal.empty')}
                        </p>
                    ) : (
                        <DataTable minWidth={640}>
                            <thead>
                                <tr>
                                    <Th>{t('cash.col.time')}</Th>
                                    <Th>{t('cash.col.kind')}</Th>
                                    <Th>{t('cash.col.account')}</Th>
                                    <Th right>{t('cash.col.in')}</Th>
                                    <Th right>{t('cash.col.out')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {movements.map((movement) => (
                                    <tr key={movement.id}>
                                        <Td className="num text-muted-foreground">
                                            {movement.time}
                                        </Td>
                                        <Td className="font-semibold">
                                            {movement.kind}
                                            {movement.note && (
                                                <span className="block text-xs font-normal text-muted-foreground">
                                                    {movement.note}
                                                </span>
                                            )}
                                        </Td>
                                        <Td>{movement.account}</Td>
                                        <Td
                                            right
                                            className="font-bold num text-success"
                                        >
                                            {movement.in}
                                        </Td>
                                        <Td
                                            right
                                            className="font-bold num text-danger"
                                        >
                                            {movement.out}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </Panel>

                {canManage && scope === 'branch' && (
                    <aside className="flex min-w-0 flex-[1_1_320px] flex-col gap-4">
                        <Panel title={t('cash.expense.title')}>
                            {categories.length > 0 && (
                                <Form
                                    {...storeExpense.form()}
                                    resetOnSuccess
                                    options={{ preserveScroll: true }}
                                    className="flex flex-col gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <SelectField
                                                label={t(
                                                    'cash.expense.category',
                                                )}
                                                name="expense_category_id"
                                                error={
                                                    errors.expense_category_id
                                                }
                                            >
                                                {categories.map((category) => (
                                                    <option
                                                        key={category.id}
                                                        value={category.id}
                                                    >
                                                        {category.name}
                                                    </option>
                                                ))}
                                            </SelectField>
                                            <Field
                                                label={t('pos.amount')}
                                                name="amount"
                                                inputMode="decimal"
                                                required
                                                placeholder="0"
                                                className="text-right num"
                                                error={errors.amount}
                                            />
                                            <SelectField
                                                label={t('cash.expense.from')}
                                                name="account"
                                                error={
                                                    errors.account ??
                                                    errors.cash_account_id
                                                }
                                            >
                                                {expenseAccounts.map(
                                                    (option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ),
                                                )}
                                            </SelectField>
                                            <Field
                                                label={t('field.note')}
                                                name="note"
                                                placeholder={t(
                                                    'cash.expense.note',
                                                )}
                                                error={errors.note}
                                            />
                                            <Button
                                                type="submit"
                                                variant="secondary"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {t('cash.expense.save')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}

                            <Form
                                {...storeCategory.form()}
                                resetOnSuccess
                                options={{ preserveScroll: true }}
                                className="flex flex-wrap items-end gap-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Field
                                            label={t('cash.category.new')}
                                            name="name"
                                            id="expense-category-name"
                                            required
                                            placeholder={t(
                                                'cash.category.placeholder',
                                            )}
                                            wrapperClassName="flex-1 basis-40"
                                            error={errors.name}
                                        />
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            disabled={processing}
                                        >
                                            {t('action.add')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </Panel>

                        <Panel
                            title={t('cash.sweep.title')}
                            description={t('cash.sweep.description')}
                            className="bg-info-bg/70"
                        >
                            <Form
                                {...sweep.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <div className="flex flex-col gap-2">
                                        <Button
                                            type="submit"
                                            size="lg"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            {t('cash.sweep.submit', {
                                                branch: branchName,
                                            })}
                                        </Button>
                                        {errors.branch && (
                                            <p className="text-sm font-semibold text-danger">
                                                {errors.branch}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </Form>
                        </Panel>
                    </aside>
                )}
            </div>
        </>
    );
}

Cash.layout = {
    breadcrumbs: [{ title: t('cash.title'), href: index() }],
    subtitle: t('cash.subtitle'),
};
