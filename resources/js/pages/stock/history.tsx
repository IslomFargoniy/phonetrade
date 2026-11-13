import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AttributeChips } from '@/components/attribute-chips';
import { Chip } from '@/components/chip';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import { show as showSale } from '@/routes/sales';
import { history } from '@/routes/stock';
import type { StockUnitRow } from '@/types';

type Props = {
    code: string;
    unit: StockUnitRow | null;
    events: {
        date: string | null;
        title: string;
        detail: string | null;
        sale_id: number | null;
    }[];
};

export default function StockHistory({ code, unit, events }: Props) {
    const [search, setSearch] = useState(code);

    return (
        <>
            <Head title={t('history.title')} />

            <Panel className="py-3.5">
                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            history().url,
                            { code: search },
                            { preserveState: true, replace: true },
                        );
                    }}
                >
                    <Input
                        aria-label={t('history.search')}
                        placeholder={t('history.search.placeholder')}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        autoFocus
                        className="min-w-0 flex-1 basis-64 num"
                    />
                    <Button type="submit" variant="secondary">
                        {t('sales.search.submit')}
                    </Button>
                </form>
            </Panel>

            {code !== '' && !unit && (
                <Panel>
                    <p className="text-sm text-ink-mid">
                        {t('history.not_found')}
                    </p>
                </Panel>
            )}

            {unit && (
                <Panel
                    title={unit.product}
                    description={`IMEI ${unit.imei1} · ${unit.barcode}`}
                    action={<Chip tone="info">{unit.status_label}</Chip>}
                >
                    <AttributeChips attributes={unit.attributes} />
                    <ol className="flex flex-col glass-inner px-4 py-1">
                        {events.map((event, i) => (
                            <li
                                key={`${event.date}-${event.title}`}
                                className={
                                    i === 0
                                        ? 'flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 py-3'
                                        : 'flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 border-t border-border py-3'
                                }
                            >
                                <span className="flex min-w-0 flex-col">
                                    <span className="text-[15px] font-bold">
                                        {event.sale_id ? (
                                            <Link
                                                href={showSale(event.sale_id)}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {event.title}
                                            </Link>
                                        ) : (
                                            event.title
                                        )}
                                    </span>
                                    {event.detail && (
                                        <span className="text-[13px] text-muted-foreground">
                                            {event.detail}
                                        </span>
                                    )}
                                </span>
                                <span className="text-[13px] num text-muted-foreground">
                                    {event.date}
                                </span>
                            </li>
                        ))}
                    </ol>
                </Panel>
            )}
        </>
    );
}

StockHistory.layout = {
    breadcrumbs: [{ title: t('history.title'), href: history() }],
    subtitle: t('history.subtitle'),
};
