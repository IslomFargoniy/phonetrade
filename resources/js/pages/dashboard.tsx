import { Head, usePage } from '@inertiajs/react';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={t('nav.home')} />

            <section className="flex flex-col gap-2 glass p-6">
                <h2 className="text-xl font-extrabold tracking-tight">
                    {t('home.greeting', { name: auth.user.name })}
                </h2>
                <p className="max-w-xl text-sm leading-relaxed text-ink-mid">
                    {t('home.empty')}
                </p>
            </section>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: t('nav.home'),
            href: dashboard(),
        },
    ],
};
