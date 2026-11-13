import { Head } from '@inertiajs/react';
import { MapPin, Phone, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { AppMark } from '@/components/app-mark';
import { AttributeChips } from '@/components/attribute-chips';
import { Chip } from '@/components/chip';
import { ProductImage } from '@/components/product-image';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Item = {
    name: string;
    image_url: string | null;
    category: string;
    attributes: Record<string, string>;
    price_label: string;
    currency: 'UZS' | 'USD';
    warranty_months: number | null;
    branch: string;
    /** Oxirgi dona qolgan */
    few: boolean;
};

type Store = {
    name: string;
    phone: string | null;
    branches: { name: string; address: string | null; phone: string | null }[];
};

type TelegramWebApp = { ready: () => void; expand: () => void };

const ALL = '__all__';

/**
 * Mijozlar uchun vitrina: do'konda hozir bor tovarlar va narxi. Faqat ko'rish uchun —
 * buyurtma berilmaydi. Telegram ichida mini-ilova sifatida ham, oddiy brauzerda ham ochiladi.
 */
export default function Storefront({
    store,
    items,
}: {
    store: Store;
    items: Item[];
}) {
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState(ALL);

    // Telegram ichida ochilganda oynani to'liq balandlikka yoyadi.
    useEffect(() => {
        const script = document.createElement('script');
        script.src = 'https://telegram.org/js/telegram-web-app.js';
        script.async = true;
        script.onload = () => {
            const app = (
                window as unknown as { Telegram?: { WebApp?: TelegramWebApp } }
            ).Telegram?.WebApp;
            app?.ready();
            app?.expand();
        };
        document.head.appendChild(script);

        return () => script.remove();
    }, []);

    const categories = useMemo(
        () => [...new Set(items.map((item) => item.category))],
        [items],
    );
    const multiBranch = store.branches.length > 1;

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return items.filter(
            (item) =>
                (category === ALL || item.category === category) &&
                (needle === '' ||
                    `${item.name} ${Object.values(item.attributes).join(' ')}`
                        .toLowerCase()
                        .includes(needle)),
        );
    }, [items, query, category]);

    return (
        <>
            <Head title={store.name} />

            <div className="flex flex-col gap-4">
                <header className="flex items-center gap-3 glass p-4">
                    <AppMark className="size-11" />
                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-extrabold tracking-tight">
                            {store.name}
                        </h1>
                        <p className="text-[13px] text-muted-foreground">
                            {t('storefront.subtitle', { count: items.length })}
                        </p>
                    </div>
                </header>

                <div className="flex flex-col gap-3 glass p-3">
                    <label className="relative block">
                        <span className="sr-only">
                            {t('storefront.search')}
                        </span>
                        <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={t('storefront.search')}
                            className="min-h-11 w-full rounded-[14px] border border-white/90 bg-white/72 pr-3 pl-10 text-[15px] text-ink outline-none focus-visible:ring-[3px] focus-visible:ring-ring"
                        />
                    </label>

                    {categories.length > 1 && (
                        <div className="flex flex-wrap gap-1.5">
                            {[ALL, ...categories].map((value) => (
                                <button
                                    key={value}
                                    type="button"
                                    aria-pressed={category === value}
                                    onClick={() => setCategory(value)}
                                    className={cn(
                                        'min-h-9 rounded-full px-3.5 text-sm font-semibold',
                                        category === value
                                            ? 'bg-primary text-white'
                                            : 'bg-white/70 text-ink-mid',
                                    )}
                                >
                                    {value === ALL
                                        ? t('storefront.all')
                                        : value}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {visible.length === 0 ? (
                    <p className="glass p-6 text-center text-sm text-muted-foreground">
                        {items.length === 0
                            ? t('storefront.empty')
                            : t('storefront.not_found')}
                    </p>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {visible.map((item, index) => (
                            <li
                                key={`${item.name}-${item.branch}-${item.price_label}-${index}`}
                                className="flex flex-col gap-2 glass p-4"
                            >
                                <ProductImage
                                    src={item.image_url}
                                    name={item.name}
                                    kind={
                                        item.category === 'Telefon'
                                            ? 'phone'
                                            : 'accessory'
                                    }
                                    className="aspect-square h-auto w-full rounded-[18px]"
                                />
                                <div className="flex items-start justify-between gap-2">
                                    <h2 className="text-base leading-snug font-bold">
                                        {item.name}
                                    </h2>
                                    {item.few && (
                                        <Chip tone="warning">
                                            {t('storefront.last')}
                                        </Chip>
                                    )}
                                </div>
                                <AttributeChips attributes={item.attributes} />
                                <div className="mt-auto flex items-end justify-between gap-2 pt-1">
                                    <span className="text-lg font-extrabold num text-primary-strong">
                                        {item.price_label}
                                    </span>
                                    <span className="text-right text-xs text-muted-foreground">
                                        {item.warranty_months
                                            ? t('storefront.warranty', {
                                                  months: item.warranty_months,
                                              })
                                            : null}
                                        {multiBranch && (
                                            <span className="block">
                                                {item.branch}
                                            </span>
                                        )}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <footer className="flex flex-col gap-3 glass p-4 text-sm">
                    <h2 className="text-[15px] font-bold">
                        {t('storefront.contacts')}
                    </h2>
                    {store.branches.map((branch) => (
                        <div key={branch.name} className="flex flex-col gap-1">
                            <span className="font-semibold">{branch.name}</span>
                            {branch.address && (
                                <span className="flex items-start gap-2 text-muted-foreground">
                                    <MapPin className="mt-0.5 size-4 shrink-0" />
                                    {branch.address}
                                </span>
                            )}
                            {(branch.phone ?? store.phone) && (
                                <a
                                    href={`tel:${(branch.phone ?? store.phone ?? '').replace(/[^\d+]/g, '')}`}
                                    className="flex items-center gap-2 font-semibold text-primary"
                                >
                                    <Phone className="size-4 shrink-0" />
                                    {branch.phone ?? store.phone}
                                </a>
                            )}
                        </div>
                    ))}
                    <p className="text-xs text-muted-foreground">
                        {t('storefront.note')}
                    </p>
                </footer>
            </div>
        </>
    );
}
