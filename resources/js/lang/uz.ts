export const uz = {
    'action.save': 'Saqlash',
    'action.cancel': 'Bekor qilish',
    'action.logout': 'Chiqish',

    'nav.title': 'Asosiy menyu',
    'nav.open': 'Menyuni ochish',
    'nav.home': 'Bosh sahifa',
    'nav.settings': 'Sozlamalar',

    'home.greeting': 'Assalomu alaykum, :name',
    'home.empty': "Bo'limlar tayyor bo'lgani sari chap menyuda paydo bo'ladi.",

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
