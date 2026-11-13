import { Form, usePage } from '@inertiajs/react';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { cancelDeletion, destroy } from '@/routes/account';

/**
 * Akkauntni o'chirish. Sotuvchi darhol o'chiriladi; egasi butun do'konni o'chirish
 * so'rovini yuboradi (superadmin bajaradi) va uni bekor qilishi mumkin.
 */
export function DeleteAccount() {
    const { auth, store } = usePage().props;
    const role = auth.user?.role;

    if (role !== 'owner' && role !== 'seller') {
        return null;
    }

    const requested = store?.deletion_requested_at ?? null;

    return (
        <section className="space-y-4 border-t border-border pt-8">
            <div>
                <h2 className="text-[17px] font-bold text-danger">
                    {t(
                        role === 'owner'
                            ? 'account.delete.store_title'
                            : 'account.delete.title',
                    )}
                </h2>
                <p className="text-[13px] leading-relaxed text-muted-foreground">
                    {t(
                        role === 'owner'
                            ? 'account.delete.store_description'
                            : 'account.delete.description',
                    )}
                </p>
            </div>

            {requested ? (
                <Form {...cancelDeletion.form()} className="space-y-3">
                    {({ processing }) => (
                        <>
                            <p className="rounded-[14px] bg-warning-bg px-3.5 py-3 text-sm font-semibold text-warning">
                                {t('account.delete.requested', {
                                    date: requested,
                                })}
                            </p>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {t('account.delete.cancel_request')}
                            </Button>
                        </>
                    )}
                </Form>
            ) : (
                <Form {...destroy.form()} className="space-y-3">
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label={t('account.delete.password')}
                                name="password"
                                id="delete-password"
                                type="password"
                                required
                                autoComplete="current-password"
                                error={errors.password}
                            />
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {t(
                                    role === 'owner'
                                        ? 'account.delete.store_submit'
                                        : 'account.delete.submit',
                                )}
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}
