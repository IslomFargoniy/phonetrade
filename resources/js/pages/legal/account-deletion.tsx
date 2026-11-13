import { Head, Link } from '@inertiajs/react';
import { AppMark } from '@/components/app-mark';
import { privacy } from '@/routes/legal';

type Props = {
    contactEmail: string | null;
    fulfilDays: number;
    updated: string;
};

export default function AccountDeletion({ contactEmail, fulfilDays }: Props) {
    return (
        <>
            <Head title="Delete your account" />

            <article className="flex flex-col gap-5 glass p-5 text-[15px] leading-relaxed sm:p-8">
                <header className="flex items-center gap-3">
                    <AppMark className="size-11" />
                    <h1 className="text-2xl font-extrabold tracking-tight">
                        PhoneTrade — delete your account
                    </h1>
                </header>

                <section className="flex flex-col gap-2">
                    <h2 className="text-[17px] font-bold">In the app</h2>
                    <ol className="list-decimal space-y-1.5 pl-5">
                        <li>Open the PhoneTrade app and sign in.</li>
                        <li>
                            Tap the profile icon in the top right corner, then{' '}
                            <b>Akkauntni o&apos;chirish</b> (Delete account).
                        </li>
                        <li>Enter your password to confirm.</li>
                    </ol>
                </section>

                <section className="flex flex-col gap-2">
                    <h2 className="text-[17px] font-bold">On the web</h2>
                    <p>
                        Sign in at this site, open <b>Sozlamalar → Profil</b>{' '}
                        (Settings → Profile) and use the delete section at the
                        bottom of the page.
                    </p>
                </section>

                <section className="flex flex-col gap-2">
                    <h2 className="text-[17px] font-bold">What is deleted</h2>
                    <ul className="list-disc space-y-1.5 pl-5">
                        <li>
                            <b>Seller account:</b> deleted immediately. Your
                            name, phone number and password are erased and you
                            are signed out on all devices. Sales you recorded
                            stay in the shop&apos;s books without your personal
                            details.
                        </li>
                        <li>
                            <b>Shop owner account:</b> a request to delete the
                            whole shop is sent. Within {fulfilDays} days we
                            permanently delete the shop with all staff accounts,
                            customers, passport photos, stock and sales records.
                            You can cancel the request in Settings → Profile
                            until it is completed.
                        </li>
                    </ul>
                </section>

                <section className="flex flex-col gap-2">
                    <h2 className="text-[17px] font-bold">Cannot sign in?</h2>
                    <p>
                        Write to{' '}
                        <span className="font-semibold select-all">
                            {contactEmail ?? '—'}
                        </span>{' '}
                        from any address and include the phone number of your
                        account. See also the{' '}
                        <Link
                            href={privacy()}
                            className="font-semibold text-primary"
                        >
                            privacy policy
                        </Link>
                        .
                    </p>
                </section>

                <section className="flex flex-col gap-2">
                    <h2 className="text-[17px] font-bold">O&apos;zbekcha</h2>
                    <p>
                        Ilovada: yuqori o&apos;ng burchakdagi profil belgisi →
                        «Akkauntni o&apos;chirish» → parolni kiriting. Webda:
                        Sozlamalar → Profil sahifasining pastida. Sotuvchi
                        akkaunti darhol o&apos;chiriladi. Do&apos;kon egasi
                        butun do&apos;konni o&apos;chirish so&apos;rovini
                        yuboradi — {fulfilDays} kun ichida bajariladi va shu
                        vaqtgacha bekor qilish mumkin.
                    </p>
                </section>
            </article>
        </>
    );
}
