import { uzInstallments } from '@/lang/uz-installments';
import { uzReports } from '@/lang/uz-reports';
import { uzSales } from '@/lang/uz-sales';
import { uzStock } from '@/lang/uz-stock';

export const uz = {
    ...uzStock,
    ...uzSales,
    ...uzInstallments,
    ...uzReports,

    'action.save': 'Saqlash',
    'action.cancel': 'Bekor qilish',
    'action.logout': 'Chiqish',

    'nav.title': 'Asosiy menyu',
    'nav.open': 'Menyuni ochish',
    'nav.settings': 'Sozlamalar',

    'security.title': 'Xavfsizlik',

    'nav.tenants': "Do'konlar",

    'action.edit': 'Tahrirlash',

    'status.active': 'Faol',
    'status.inactive': 'Faol emas',
    'status.blocked': 'Bloklangan',

    'rate.today': 'Dollar kursi bugun',
    'rate.last': 'Oxirgi dollar kursi',
    'rate.value': ":value so'm",
    'rate.missing': 'Kiritilmagan',

    'branch.switch': 'Filialni almashtirish',
    'branch.none': 'Filial tanlanmagan',

    'tenants.col.plan': 'Tarif',
    'tenants.col.paid_until': 'Obuna',
    'tenants.manage': 'Obuna',
    'tenants.stats.tenants': "Do'konlar",
    'tenants.stats.expired': 'Muddati tugagan',
    'tenants.stats.grace': 'Imtiyozli davrda',
    'tenants.stats.blocked': 'Bloklangan',
    'tenants.stats.branches': 'Filiallar',
    'tenants.stats.users': 'Foydalanuvchilar',
    'tenants.stats.sales_month': 'Shu oydagi savdolar',
    'tenants.stats.income_month': 'Shu oydagi obuna tushumi',
    'billing.title': "Do'kon obunasi",
    'billing.subscription': 'Obuna',
    'billing.state.unlimited': 'Muddatsiz',
    'billing.state.active': 'Faol',
    'billing.state.grace': 'Imtiyozli davr',
    'billing.state.expired': 'Muddati tugagan',
    'billing.plan': 'Tarif',
    'billing.no_plan': 'Tarifsiz (cheklovsiz)',
    'billing.choose_plan': 'Tarifni tanlang',
    'billing.paid_until': "To'langan muddat",
    'billing.no_expiry': "Muddat qo'yilmagan",
    'billing.sellers': 'Sotuvchilar',
    'billing.history': "To'lovlar tarixi",
    'billing.history.empty': "Hali to'lov kiritilmagan.",
    'billing.col.date': 'Sana',
    'billing.col.period': 'Davr',
    'billing.amount': "Summa, so'm",
    'billing.months': ':months oy',
    'billing.months_count': 'Oylar soni',
    'billing.note': 'Izoh (ixtiyoriy)',
    'billing.pay.title': "To'lov qabul qilish",
    'billing.pay.description':
        "Muddat hali tugamagan bo'lsa uning ustiga, tugagan bo'lsa bugundan boshlab qo'shiladi.",
    'billing.pay.submit': "To'lovni yozish",
    'billing.manual.title': "Qo'lda o'zgartirish",
    'billing.manual.description':
        "To'lovsiz: sinov muddati berish, tarifni almashtirish yoki xatoni tuzatish.",
    'billing.manual.hint':
        "Bo'sh qoldirilsa muddat cheklanmaydi. Muddatdan keyin 5 kun imtiyozli davr, so'ng faqat o'qish rejimi.",
    'billing.back': "Do'konlar ro'yxatiga",
    'plans.title': 'Tariflar',
    'plans.subtitle': 'Obuna tariflari va limitlari',
    'plans.name': 'Nomi',
    'plans.price': "Oylik narx, so'm",
    'plans.max_branches': 'Filiallar soni',
    'plans.max_users': 'Sotuvchilar soni',
    'plans.active': "Yangi to'lovlarda tanlash mumkin",
    'plans.tenants': ":count ta do'kon shu tarifda",
    'plans.new': 'Yangi tarif',
    'plans.new.description': "Limit bo'sh qoldirilsa cheklanmaydi.",
    'plans.create': "Tarif qo'shish",
    'subscription.expired':
        "Obuna muddati tugagan: do'kon faqat o'qish rejimida. Do'kon egasiga murojaat qiling.",
    'subscription.expired.owner':
        "Obuna muddati tugagan: do'kon faqat o'qish rejimida. Savdo va boshqa yozuvlar to'lovdan keyin ochiladi.",
    'subscription.grace':
        "Obuna muddati tugagan. :date dan boshlab do'kon faqat o'qish rejimiga o'tadi — to'lovni amalga oshiring.",
    'subscription.ends_soon': 'Obuna muddati :days kundan keyin tugaydi.',
    'subscription.ends_today': 'Obuna muddati bugun tugaydi.',
    'tenants.title': "Do'konlar",
    'tenants.subtitle': "Platformadagi barcha do'konlar",
    'tenants.list': "Ro'yxat",
    'tenants.empty': "Hali bironta do'kon ochilmagan.",
    'tenants.col.store': "Do'kon",
    'tenants.col.owner': 'Egasi',
    'tenants.col.branches': 'Filiallar',
    'tenants.col.users': 'Xodimlar',
    'tenants.col.status': 'Holat',
    'tenants.block': 'Bloklash',
    'tenants.unblock': 'Blokdan chiqarish',
    'tenants.new': "Yangi do'kon",
    'tenants.new.description':
        "Do'kon, egasining akkaunti va birinchi filial birga yaratiladi.",
    'tenants.field.name': "Do'kon nomi",
    'tenants.field.branch': 'Birinchi filial nomi',
    'tenants.field.owner': 'Egasining ismi',
    'tenants.field.phone': 'Egasining telefoni',
    'tenants.field.password.hint':
        "Kamida 8 belgi. Egasi keyin o'zi almashtiradi.",
    'tenants.create': "Do'konni ochish",

    'branches.title': 'Filiallar',
    'branches.description':
        'Har filial uchta kassa va nasiya ustamasi bilan birga ochiladi.',
    'branches.staff': ':count ta xodim',
    'branches.new': 'Yangi filial',
    'branches.create': 'Filial ochish',
    'branches.field.name': 'Filial nomi',
    'branches.field.state': 'Filial holati',

    'staff.title': 'Xodimlar',
    'staff.description':
        "Sotuvchi faqat biriktirilgan filialda ishlaydi, tannarx va foydani ko'rmaydi.",
    'staff.all_branches': 'Barcha filiallar',
    'staff.new': 'Yangi sotuvchi',
    'staff.create': "Sotuvchi qo'shish",
    'staff.field.branches': 'Filiallar',
    'staff.field.state': 'Xodim holati',
    'staff.field.password.hint': "Kamida 8 belgi. Sotuvchiga o'zingiz aytasiz.",
    'staff.field.new_password': 'Yangi parol',
    'staff.field.new_password.hint': "Bo'sh qoldirilsa parol o'zgarmaydi.",
    'staff.state.off': "O'chirilgan (kira olmaydi)",

    'store.title': "Do'kon sozlamalari",
    'store.tab': "Do'kon",
    'store.rate.title': 'Dollar kursi',
    'store.rate.description':
        "Yangi kurs keyingi savdolarga ta'sir qiladi. Eski kirim va savdolar o'z kursida qoladi.",
    'store.rate.field': "1 $ = so'm",
    'store.rate.manual': "Qo'lda",
    'store.rate.cbu': 'Markaziy bank',
    'store.telegram.title': 'Telegram bot',
    'store.telegram.description':
        "Har kuni kechqurun savdo va foyda hisoboti, muddati o'tgan to'lovlar ro'yxati. Botga ulangan mijozlarga to'lov kunidan oldin eslatma boradi.",
    'store.telegram.linked': 'Ulangan',
    'store.telegram.linked_hint':
        "Hisobot shu akkauntning Telegram'iga yuboriladi. Botda: /hisobot, /qarzdorlar.",
    'store.telegram.link_hint':
        "Tugmani bosing va Telegram'da «Start» ni bosing. Havola 30 daqiqa amal qiladi.",
    'store.telegram.link': "Telegram'ga ulash",
    'store.telegram.unlink': 'Uzish',
    'store.telegram.disabled':
        "Bot hali sozlanmagan. Tizim administratori bot tokenini o'rnatgach, shu yerda ulash tugmasi paydo bo'ladi.",
    'store.storefront.title': 'Mijozlar uchun vitrina',
    'store.storefront.description':
        "Mijozlar do'konda hozir bor tovarlar va narxlarni Telegram ichida ko'radi. Faqat ko'rish uchun: buyurtma berilmaydi, tannarx va IMEI ko'rinmaydi.",
    'store.storefront.on': 'Ochiq',
    'store.storefront.enable': 'Vitrinani ochish',
    'store.storefront.disable': 'Vitrinani yopish',
    'store.storefront.telegram_link':
        'Mijozlarga tarqatiladigan havola (Telegram)',
    'store.storefront.web_link': "To'g'ridan-to'g'ri havola (brauzer)",
    'storefront.subtitle': ':count xil tovar mavjud',
    'storefront.search': 'Model yoki xususiyat',
    'storefront.all': 'Hammasi',
    'storefront.empty': "Hozircha tovar yo'q.",
    'storefront.not_found': 'Hech narsa topilmadi.',
    'storefront.last': 'Oxirgi dona',
    'storefront.warranty': 'Kafolat :months oy',
    'storefront.contacts': 'Manzil va aloqa',
    'storefront.note':
        "Narxlar va mavjudlik do'kondagi hozirgi holatni ko'rsatadi. Xarid do'konda amalga oshiriladi.",
    'store.general.title': 'Yaxlitlash va chek',
    'store.general.description':
        "Nasiyada oylik to'lov shu qadamga yuqoriga yaxlitlanadi.",
    'store.general.round_uzs': "Yaxlitlash, so'm",
    'store.general.round_usd': 'Yaxlitlash, $',
    'store.general.receipt_name': "Chekdagi do'kon nomi",
    'store.general.receipt_phone': 'Chekdagi telefon',
    'store.general.receipt_footer': 'Chek ostidagi matn',
    'store.markup.title': 'Nasiya ustamasi',
    'store.markup.description':
        "Sotuvchi faqat shu to'rt muddatdan birini tanlaydi.",
    'store.markup.months': ':months oy, %',

    'field.address': 'Manzil',
    'field.name': 'Ism',
    'field.phone': 'Telefon raqami',
    'field.password': 'Parol',

    'auth.login.head': 'Kirish',
    'auth.login.title': 'Tizimga kirish',
    'auth.login.description': 'Telefon raqamingiz va parolingizni kiriting',
    'auth.login.remember': 'Eslab qolish',
    'auth.login.submit': 'Kirish',

    'profile.head': 'Profil sozlamalari',
    'profile.title': 'Profil',
    'profile.description': 'Ism va telefon raqamingizni yangilang',
} as const;
