import { Head, Link } from '@inertiajs/react';
import { AppMark } from '@/components/app-mark';
import { accountDeletion } from '@/routes/legal';

type Props = {
    contactEmail: string | null;
    fulfilDays: number;
    updated: string;
};

/**
 * Maxfiylik siyosati. Ilova do'konlari sharhlovchilari o'qishi uchun inglizcha,
 * foydalanuvchilar uchun o'zbekcha qisqacha mazmuni bilan.
 */
export default function Privacy({ contactEmail, fulfilDays, updated }: Props) {
    return (
        <>
            <Head title="Privacy Policy" />

            <article className="flex flex-col gap-5 glass p-5 text-[15px] leading-relaxed sm:p-8">
                <header className="flex items-center gap-3">
                    <AppMark className="size-11" />
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight">
                            PhoneTrade — Privacy Policy
                        </h1>
                        <p className="text-[13px] text-muted-foreground">
                            Last updated: {updated}
                        </p>
                    </div>
                </header>

                <p>
                    PhoneTrade is a sales and inventory system for mobile phone
                    shops. It is used by shop owners and their staff through the
                    web panel, the Android app and the iOS app. This policy
                    explains what data the service processes and why.
                </p>

                <Section title="Who uses the service">
                    <p>
                        Accounts are created by the platform administrator (shop
                        owners) and by shop owners (their sellers). There is no
                        public sign-up. Shop customers do not have accounts;
                        their details are entered by shop staff.
                    </p>
                </Section>

                <Section title="Data we process">
                    <ul className="list-disc space-y-1.5 pl-5">
                        <li>
                            <b>Staff account data:</b> name, phone number and a
                            hashed password, used to sign in.
                        </li>
                        <li>
                            <b>Shop business data:</b> products, phone IMEI
                            numbers, stock, sales, payments, expenses and cash
                            balances entered by the shop.
                        </li>
                        <li>
                            <b>Shop customer data</b> (entered by staff when a
                            customer buys in instalments): full name, phone
                            numbers, address, passport series and number,
                            personal identification number, a photo of the
                            passport, and the instalment payment history.
                        </li>
                        <li>
                            <b>Camera:</b> the mobile apps use the camera only
                            to scan IMEI numbers and barcodes and, in the
                            Android app, to photograph a customer&apos;s
                            passport for an instalment contract. Photos are
                            uploaded to the shop&apos;s account and are not
                            saved to the device gallery.
                        </li>
                        <li>
                            <b>Bluetooth</b> (Android): used only to print
                            receipts on a paired receipt printer.
                        </li>
                        <li>
                            <b>Telegram:</b> if a shop owner or a customer links
                            the Telegram bot, we store their Telegram chat
                            identifier to send reports and payment reminders.
                            Linking can be undone at any time with the /stop
                            command.
                        </li>
                    </ul>
                    <p>
                        We do not collect location, contacts, advertising
                        identifiers or analytics, and the apps contain no ads.
                    </p>
                </Section>

                <Section title="How data is used and shared">
                    <p>
                        Data is used only to provide the service to the shop
                        that entered it. Each shop sees only its own data. We do
                        not sell data or share it with third parties, except
                        Telegram for delivering messages the user has asked for.
                        Data is transmitted over an encrypted connection
                        (HTTPS).
                    </p>
                </Section>

                <Section title="Retention and deletion">
                    <p>
                        Data is kept while the shop uses the service. A seller
                        can delete their own account in the app or the web panel
                        (Settings → Profile). A shop owner can request deletion
                        of the whole shop, including all staff accounts,
                        customers, passport photos and sales records; the
                        request is completed within {fulfilDays} days. See{' '}
                        <Link
                            href={accountDeletion()}
                            className="font-semibold text-primary"
                        >
                            account deletion
                        </Link>
                        . Shop customers can ask the shop, or us, to delete
                        their record.
                    </p>
                </Section>

                <Section title="Children">
                    <p>
                        The service is a business tool and is not intended for
                        anyone under 18.
                    </p>
                </Section>

                <Section title="Contact">
                    <p>
                        Questions and deletion requests:{' '}
                        <span className="font-semibold select-all">
                            {contactEmail ?? '—'}
                        </span>
                    </p>
                </Section>

                <Section title="Qisqacha (o'zbekcha)">
                    <p>
                        PhoneTrade — telefon do&apos;konlari uchun savdo va
                        ombor tizimi. Tizim xodimlarning ismi va telefon
                        raqamini, do&apos;konning savdo va ombor
                        ma&apos;lumotlarini, muddatli to&apos;lov olgan
                        mijozlarning ismi, telefoni, manzili, pasport
                        ma&apos;lumoti va pasport rasmini saqlaydi. Kamera faqat
                        skanerlash va pasportni suratga olish uchun ishlatiladi.
                        Ma&apos;lumotlar uchinchi shaxslarga berilmaydi va
                        sotilmaydi; har bir do&apos;kon faqat o&apos;z
                        ma&apos;lumotini ko&apos;radi. Akkauntni ilova yoki web
                        paneldagi Sozlamalar → Profil bo&apos;limida
                        o&apos;chirish mumkin.
                    </p>
                </Section>
            </article>
        </>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-2">
            <h2 className="text-[17px] font-bold">{title}</h2>
            {children}
        </section>
    );
}
