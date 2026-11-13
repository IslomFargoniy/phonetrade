import { Form, Head } from '@inertiajs/react';
import { Chip } from '@/components/chip';
import { Field } from '@/components/field';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    edit,
    general,
    installmentRates,
    rate as rateRoute,
} from '@/routes/store-settings';
import { unlink as unlinkTelegram } from '@/routes/store-settings/telegram';

type RateRow = {
    date: string;
    usd_uzs: string;
    source: 'cbu' | 'manual';
};

type BranchRates = {
    id: number;
    name: string;
    rates: { months: number; markup_pct: string }[];
};

type Props = {
    rateHistory: RateRow[];
    currentRate: string | null;
    branches: BranchRates[];
    roundStepUzs: string;
    roundStepUsd: string;
    receipt: { name: string; phone: string | null; footer: string | null };
    telegram: { enabled: boolean; linked: boolean; link: string | null };
};

export default function StoreSettings({
    rateHistory,
    currentRate,
    branches,
    roundStepUzs,
    roundStepUsd,
    receipt,
    telegram,
}: Props) {
    return (
        <>
            <Head title={t('store.title')} />

            <div className="gap-4 xl:columns-2 [&>*]:mb-4 [&>*]:break-inside-avoid">
                <Panel
                    title={t('store.rate.title')}
                    description={t('store.rate.description')}
                >
                    <Form
                        {...rateRoute.form()}
                        options={{ preserveScroll: true }}
                        className="flex flex-wrap items-end gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    label={t('store.rate.field')}
                                    name="usd_uzs"
                                    inputMode="decimal"
                                    required
                                    defaultValue={
                                        currentRate
                                            ? formatNumber(currentRate).replace(
                                                  / /g,
                                                  '',
                                              )
                                            : ''
                                    }
                                    className="text-lg font-bold num"
                                    wrapperClassName="flex-1 basis-40"
                                    error={errors.usd_uzs}
                                />
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('action.save')}
                                </Button>
                            </>
                        )}
                    </Form>

                    {rateHistory.length > 0 && (
                        <div className="flex flex-col glass-inner px-3.5 py-1">
                            {rateHistory.map((row, i) => (
                                <div
                                    key={row.date}
                                    className={
                                        i === 0
                                            ? 'flex items-center justify-between gap-3 py-2.5 text-sm'
                                            : 'flex items-center justify-between gap-3 border-t border-border py-2.5 text-sm'
                                    }
                                >
                                    <span className="num text-muted-foreground">
                                        {row.date}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <Chip
                                            tone={
                                                row.source === 'manual'
                                                    ? 'info'
                                                    : 'neutral'
                                            }
                                        >
                                            {row.source === 'manual'
                                                ? t('store.rate.manual')
                                                : t('store.rate.cbu')}
                                        </Chip>
                                        <span className="font-bold num">
                                            {formatNumber(row.usd_uzs)}
                                        </span>
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </Panel>

                <Panel
                    title={t('store.general.title')}
                    description={t('store.general.description')}
                >
                    <Form
                        {...general.form()}
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        label={t('store.general.round_uzs')}
                                        name="round_step_uzs"
                                        inputMode="numeric"
                                        required
                                        defaultValue={roundStepUzs}
                                        className="num"
                                        error={errors.round_step_uzs}
                                    />
                                    <Field
                                        label={t('store.general.round_usd')}
                                        name="round_step_usd"
                                        inputMode="numeric"
                                        required
                                        defaultValue={roundStepUsd}
                                        className="num"
                                        error={errors.round_step_usd}
                                    />
                                </div>
                                <Field
                                    label={t('store.general.receipt_name')}
                                    name="receipt_name"
                                    required
                                    defaultValue={receipt.name}
                                    error={errors.receipt_name}
                                />
                                <Field
                                    label={t('store.general.receipt_phone')}
                                    name="receipt_phone"
                                    defaultValue={receipt.phone ?? ''}
                                    placeholder="+998"
                                    error={errors.receipt_phone}
                                />
                                <Field
                                    label={t('store.general.receipt_footer')}
                                    name="receipt_footer"
                                    defaultValue={receipt.footer ?? ''}
                                    error={errors.receipt_footer}
                                />
                                <Button
                                    type="submit"
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

                <Panel
                    title={t('store.telegram.title')}
                    description={t('store.telegram.description')}
                    action={
                        telegram.linked ? (
                            <Chip tone="success">
                                {t('store.telegram.linked')}
                            </Chip>
                        ) : undefined
                    }
                >
                    {!telegram.enabled ? (
                        <p className="text-sm text-muted-foreground">
                            {t('store.telegram.disabled')}
                        </p>
                    ) : telegram.linked ? (
                        <Form
                            {...unlinkTelegram.form()}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-3"
                        >
                            {({ processing }) => (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        {t('store.telegram.linked_hint')}
                                    </p>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="self-start"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('store.telegram.unlink')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <div className="flex flex-col gap-3">
                            <p className="text-sm text-muted-foreground">
                                {t('store.telegram.link_hint')}
                            </p>
                            {telegram.link && (
                                <Button asChild className="self-start">
                                    <a
                                        href={telegram.link}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {t('store.telegram.link')}
                                    </a>
                                </Button>
                            )}
                        </div>
                    )}
                </Panel>

                {branches.map((branch) => (
                    <Panel
                        key={branch.id}
                        title={t('store.markup.title')}
                        description={t('store.markup.description')}
                        action={<Chip>{branch.name}</Chip>}
                    >
                        <Form
                            {...installmentRates.form(branch.id)}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        {branch.rates.map((row, i) => (
                                            <div key={row.months}>
                                                <input
                                                    type="hidden"
                                                    name={`rates[${i}][months]`}
                                                    value={row.months}
                                                />
                                                <Field
                                                    label={t(
                                                        'store.markup.months',
                                                        { months: row.months },
                                                    )}
                                                    name={`rates[${i}][markup_pct]`}
                                                    id={`markup-${branch.id}-${row.months}`}
                                                    inputMode="decimal"
                                                    required
                                                    defaultValue={
                                                        row.markup_pct
                                                    }
                                                    className="num"
                                                    error={
                                                        errors[
                                                            `rates.${i}.markup_pct`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </div>
                                    <Button
                                        type="submit"
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
            </div>
        </>
    );
}

StoreSettings.layout = {
    breadcrumbs: [{ title: t('store.title'), href: edit() }],
};
