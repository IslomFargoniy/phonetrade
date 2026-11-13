import { Head, router } from '@inertiajs/react';
import { Chip } from '@/components/chip';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { ProductImage } from '@/components/product-image';
import { t } from '@/lib/i18n';
import { index, receive } from '@/routes/transfers';

type Transfer = {
    id: number;
    from: string;
    to: string;
    incoming: boolean;
    status: 'in_transit' | 'received';
    status_label: string;
    created_at: string | null;
    note: string | null;
    items: {
        id: number;
        name: string | null;
        image_url: string | null;
        is_phone: boolean;
        code: string | null;
        qty: number;
    }[];
};

export default function Transfers({ transfers }: { transfers: Transfer[] }) {
    return (
        <>
            <Head title={t('transfers.title')} />

            {transfers.length === 0 ? (
                <Panel>
                    <p className="text-sm text-ink-mid">
                        {t('transfers.empty')}
                    </p>
                </Panel>
            ) : (
                <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
                    {transfers.map((transfer) => (
                        <Panel
                            key={transfer.id}
                            title={`${transfer.from} → ${transfer.to}`}
                            description={transfer.created_at ?? undefined}
                            action={
                                <Chip
                                    tone={
                                        transfer.status === 'in_transit'
                                            ? 'warning'
                                            : 'success'
                                    }
                                >
                                    {transfer.status_label}
                                </Chip>
                            }
                        >
                            <ul className="flex flex-col glass-inner px-3.5 py-1 text-sm">
                                {transfer.items.map((item, i) => (
                                    <li
                                        key={item.id}
                                        className={
                                            i === 0
                                                ? 'flex items-center justify-between gap-3 py-2.5'
                                                : 'flex items-center justify-between gap-3 border-t border-border py-2.5'
                                        }
                                    >
                                        <ProductImage
                                            src={item.image_url}
                                            name={item.name ?? ''}
                                            kind={
                                                item.is_phone
                                                    ? 'phone'
                                                    : 'accessory'
                                            }
                                            className="size-10 rounded-[12px]"
                                        />
                                        <span className="flex min-w-0 flex-1 flex-col">
                                            <span className="font-semibold">
                                                {item.name}
                                            </span>
                                            <span className="text-xs num text-muted-foreground">
                                                {item.code}
                                            </span>
                                        </span>
                                        <span className="font-bold num">
                                            {t('stock.qty', {
                                                count: item.qty,
                                            })}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            {transfer.incoming &&
                                transfer.status === 'in_transit' && (
                                    <Button
                                        className="self-start"
                                        onClick={() =>
                                            router.post(
                                                receive(transfer.id).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('transfers.receive')}
                                    </Button>
                                )}
                        </Panel>
                    ))}
                </div>
            )}
        </>
    );
}

Transfers.layout = {
    breadcrumbs: [{ title: t('transfers.title'), href: index() }],
    subtitle: t('transfers.subtitle'),
};
