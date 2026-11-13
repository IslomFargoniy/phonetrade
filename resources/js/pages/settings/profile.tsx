import { DeleteAccount } from '@/components/delete-account';
import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { formatPhone } from '@/lib/phone';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title={t('profile.head')} />

            <h1 className="sr-only">{t('profile.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('profile.title')}
                    description={t('profile.description')}
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('field.name')}</Label>
                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder={t('field.name')}
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    {t('field.phone')}
                                </Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    className="mt-1 block w-full"
                                    defaultValue={formatPhone(auth.user.phone)}
                                    name="phone"
                                    required
                                    autoComplete="tel"
                                    inputMode="tel"
                                    placeholder="+998 90 123 45 67"
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.phone}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {t('action.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <DeleteAccount />
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: t('profile.head'),
            href: edit(),
        },
    ],
};
