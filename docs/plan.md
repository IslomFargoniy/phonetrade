# Telefon do'koni savdo tizimi — ishlab chiqish rejasi

Bu hujjat kod yozish uchun ish rejasi. Biznes qoidalari "Telefon savdo ilovasi" chatida kelishilgan va quyidagi manbalarda yozilgan:

| Manba | Havola |
|---|---|
| Texnik reja (asosiy manba, barcha qoidalar) | https://claude.ai/artifact/ErBBbXqdsfbqEDoa9FAncr |
| Dizayn — Liquid Glass (yakuniy): web, Android, iOS | https://claude.ai/artifact/NSGoB75kDs6amqhCDCGKtp |
| Dizayn — birinchi variant (rad etilgan, faqat ma'lumot uchun) | https://claude.ai/artifact/4nfWVQSsaHYG4eD5EWY2g7 |

Qoida va bu hujjat orasida ziddiyat chiqsa, texnik reja ustun.

---

## 1. Mahsulot

Telefon va aksesuar do'konlari uchun SaaS savdo tizimi. Superadmin (Islombek) har bir mijozga alohida do'kon (tenant) ochib beradi. Har do'konda bir nechta filial va xodim bo'ladi.

| Rol | Kim | Nima qiladi | Nimani ko'rmaydi |
|---|---|---|---|
| Superadmin | Islombek | Do'kon ochish va bloklash, tarif, obuna to'lovlari, umumiy statistika | Do'kon savdolarini tahrirlay olmaydi (faqat ko'radi) |
| Do'kon egasi | Mijoz-do'kon | Hammasi: filiallar, xodimlar, kirim, narx, ustama jadvali, xarajat, hisobotlar | — |
| Sotuvchi | Xodim | O'z filialida sotuv, nasiya, nasiya to'lovini qabul qilish, mijoz qo'shish, qoldiqni ko'rish | Tannarx, foyda, kirim narxi, boshqa filial kassasi, xarajatlar |

Sotuvchi uchun tannarx va foyda maydonlari API va Inertia javobidan umuman chiqarilmaydi — faqat UI'da yashirish yetarli emas. Filial menejeri roli yo'q.

---

## 2. Biznes qoidalari (qisqa)

To'liq matn texnik rejada. Bu yerda kodga bevosita ta'sir qiladiganlari.

### Tovar va ombor
- Telefon donalab hisoblanadi: IMEI 1, IMEI 2, ichki barcode (Code128, do'kon prefiksi + tartib raqami), kriteriyalar, tannarx, sotuv narxi, max chegirma %, kafolat, filial, holat (omborda / band / sotilgan / qaytgan / ko'chirilmoqda).
- Aksesuar miqdor bilan: bitta barcode, har kirim alohida partiya. Tannarx **FIFO** bo'yicha.
- Kriteriyalarni do'kon egasi sozlaydi (rang, xotira, RAM, holat, batareya %, komplekt); turi: ro'yxat, son yoki matn.
- IMEI yoki ichki barcode'ni skanerlash bir xil donani topadi.
- Kirim 3 turda:

| Tur | Yetkazib beruvchi | Kassa |
|---|---|---|
| Yetkazib beruvchidan | Bor | To'lov alohida chiqim sifatida yoziladi |
| Boshlang'ich qoldiq | Yo'q | Pul chiqmaydi |
| Mijozdan sotib olish | Mijoz | Tanlangan kassadan chiqim; qoldiq yetmasa amal bajarilmaydi. Telefon darhol omborga tushadi. |

- 10 ta bir xil telefon bitta kirimda: umumiy ma'lumot bir marta, keyin 10 ta IMEI skaner yoki Excel'dan. Tizim 10 ta dona va 10 ta barcode yaratadi.
- Max chegirma kirimda belgilanadi va **qat'iy limit**: oshirish, so'rov yoki tasdiqlash yo'q.
- Filiallar orasida ko'chirish: tovar "yo'lda" holatiga o'tadi, qabul qiluvchi filial tasdiqlaydi.

### Sotuv
- To'liq to'lov (mijoz ixtiyoriy) yoki muddatli (mijoz majburiy).
- To'lov qatorlari: naqd / karta / o'tkazma × so'm / dollar. Har qator o'z valyutasidagi kassa balansiga tushadi va o'z kursini saqlaydi.
- Savdo jami: savat bir valyutada bo'lsa shu valyutada; aralash bo'lsa so'mda (dollarli tovar savdo kunidagi kurs bilan). Qaytim savdo valyutasida.
- To'lovlar yig'indisi jamiga (nasiyada — savdo kuni to'lanadigan summaga) teng bo'lmaguncha savdo yopilmaydi.
- Mijoz eski telefonini topshirsa — bu ikki alohida amal: avval kirim (kassadan chiqim), keyin oddiy sotuv. Bir-biriga bog'lanmaydi, "trade-in" to'lov turi yo'q.
- Qaytarish: to'liq yoki qisman, muddat cheklovi yo'q. Faqat egasi yoki u ruxsat bergan xodim. Pul kassadan qaytadi, foyda teskari yoziladi. Nasiyali savdo qaytsa shartnoma bekor bo'ladi.
- Kafolat tugash sanasi sotuv qatorida saqlanadi.

### Muddatli to'lov
- Har filialda 3, 6, 9, 12 oylik ustama qatorlari avtomatik yaratiladi; foizni egasi belgilaydi. Boshqa oy tanlab bo'lmaydi.
- To'lov kuni 1–28, savdoda tanlanadi, savdo sanasiga yaqinligi cheklanmaydi.
- Valyuta shartnomada tanlanadi; savat aralash bo'lsa so'm. Dollarli qarz to'lov kunidagi kurs bilan so'mda ham qabul qilinadi.
- Boshlang'ich to'lov faqat savdo paytida.

| | A rejim — boshlang'ichsiz | B rejim — boshlang'ich bilan |
|---|---|---|
| Ustama | Butun narxga | Faqat qoldiqqa |
| Savdo kuni to'lanadi | 1-oylik | Boshlang'ich |
| Oyliklar | N ta, 1-chisi savdo kuni | N ta, keyingi oydan |

```
qarz   = (narx − boshlang'ich) × (1 + ustama%)
oylik  = qarz / N, yuqoriga yaxlitlanadi (10 000 so'm yoki 10 $)
oxirgi = qarz − oylik × (N − 1)
```

- Ustama bir marta hisoblanadi va o'smaydi: jarima yo'q, qo'shimcha foiz yo'q. Muddatidan oldin yopilsa ham kamaymaydi.
- Mijoz istalgan summani to'laydi; pul eng eski to'lanmagan oylikdan boshlab taqsimlanadi, ortiqchasi keyingi oyliklarni yopadi.
- "Muddati o'tgan" alohida holat emas — to'lanmagan oylik sanasidan hisoblanadi. Shartnoma holatlari: Faol → Yopilgan yoki Bekor qilingan.
- Mijoz kartasi: F.I.Sh, telefon, qo'shimcha telefon, pasport, JSHSHIR, manzil, pasport rasmlari (yopiq diskda).

### Foyda
- Har sotuv qatorida tannarx nusxasi saqlanadi; keyingi o'zgarishlar eski savdoga ta'sir qilmaydi.
- Dollarda olingan tovar tannarxi **kirim kunidagi kurs** bilan so'mga o'tkaziladi.
- To'liq savdo: foyda savdo kunida.
- Nasiya: foyda pul tushgan sari. Shartnomada `ulush = (jami tushum − tannarx) / jami tushum` bir marta hisoblanadi, har to'lovning shu ulushi foyda. Yaxlitlash farqi oxirgi to'lovda.
- Kutilayotgan foyda = faol shartnomalarda qolgan qarz × ulush.
- Sof foyda = yalpi foyda − xarajatlar.

### Kassa
- Har filialda 3 kassa (naqd, karta, o'tkazma), har birida so'm va dollar balansi. Egasida har turdan bittadan admin kassa.
- Smena yo'q.
- Har harakat `cash_movements` ga yoziladi; balans harakatlardan hisoblanadi, qo'lda o'zgartirilmaydi. Xato teskari yozuv bilan tuzatiladi.
- Har qanday chiqim qoldiqdan oshsa bajarilmaydi.
- Kassa bo'shatish: filial kassasining butun qoldig'i shu turdagi admin kassaga o'tadi. Admin kassa o'chirilmaydi va tahrirlanmaydi.

### Kurs va obuna
- Kuniga bitta kurs. Ertalab Markaziy bank kursi avtomatik olinadi, egasi o'zgartira oladi.
- Tarif: filial va xodim limiti, oylik narx. Muddat tugagach imtiyozli davr, keyin faqat o'qish rejimi; ma'lumot o'chmaydi.

---

## 3. Arxitektura

```
 Web (React + Inertia) ─┐
 Android (Kotlin)  ─────┤                      ┌─ MySQL/PostgreSQL (tenant_id)
 iOS (SwiftUI)  ────────┼─▶ Laravel 13 ────────┼─ Yopiq disk (pasport rasmlari)
 Telegram bot  ─────────┤   app/Services       └─ Queue (hisobot, bot, kurs)
 Chek / etiketka printer┘
```

1. **Multi-tenant bitta bazada.** `tenants`, `subscriptions`, `subscription_payments` dan tashqari har jadvalda `tenant_id`; Laravel global scope ajratadi. Har tenant ma'lumoti boshqasiga sizmasligi alohida testlar bilan tekshiriladi.
2. **Hisob-kitob faqat backendda**, `app/Services` ichida. Web, Android va iOS faqat natijani ko'rsatadi; oldindan ko'rish uchun `preview` endpointlar.
3. **Pul** `decimal(18,2)`, har summa yonida `currency` va `rate`. PHP'da float bilan hisoblanmaydi (bcmath yoki butun son).
4. **Savdoni yopish atomar:** bitta DB tranzaksiyada dona band qilinadi, to'lovlar kassaga tushadi, nasiya jadvali yaratiladi. IMEI va barcode tenant ichida unique.
5. **Mobil API:** `routes/api.php`, `/api/v1`, Sanctum token. Pul yozadigan har so'rovda `Idempotency-Key`.
6. **Audit:** narx o'zgartirish, qaytarish, o'chirish `audit_logs` ga yoziladi.

### Hozirgi holat

| Narsa | Holat |
|---|---|
| Laravel loyiha | React starter kit o'rnatilgan: Laravel 13, Inertia 3, React 19, Tailwind 4, Fortify (2FA, passkey), Wayfinder, PHPUnit, Larastan. Biznes kodi yo'q. |
| `android/`, `ios/` | Papkalar ochilgan, bo'sh |
| Git | Hali yo'q |
| Baza | Lokal SQLite; productionda almashtiriladi |
| Server | `phonetrade.simulator.uz`, FastPanel, PHP-FPM 8.4 yoqilgan. Root hali `public/` ga o'tkazilmagan, baza ochilmagan. Serverda PostgreSQL 16 va MariaDB 10.6 bor. |

Starter kit'dan o'zgaradi: ochiq ro'yxatdan o'tish o'chiriladi (do'konni superadmin, xodimni egasi yaratadi); login telefon raqami bilan.

---

## 4. Ma'lumotlar modeli

Jadvallar texnik rejadagi sxema bo'yicha:

| Guruh | Jadvallar |
|---|---|
| Tenant | `tenants`, `subscriptions`, `subscription_payments` |
| Tashkilot | `branches`, `users`, `branch_user`, `settings`, `installment_rates`, `currency_rates` |
| Katalog | `categories`, `attributes`, `attribute_options`, `products`, `product_variants` |
| Kirim va ombor | `suppliers`, `purchases`, `purchase_items`, `stock_units`, `stock_batches`, `stock_transfers`, `stock_transfer_items` |
| Mijoz | `customers`, `customer_files` |
| Savdo | `sales`, `sale_items`, `sale_payments`, `returns`, `return_items` |
| Nasiya | `installment_plans`, `installment_schedule`, `installment_payments` |
| Kassa | `cash_accounts`, `cash_movements`, `expense_categories`, `expenses` |
| Audit | `audit_logs` |

Muhim maydonlar: `sale_items.cost_snapshot`, `installment_plans.profit_ratio`, `installment_payments.profit_amount` (to'lov paytida yoziladi — hisobotlar oddiy yig'indi bilan chiqadi), `cash_accounts.branch_id = null` — admin kassa.

---

## 5. Platformalar va ekranlar

| Platforma | Kim | Vazifa |
|---|---|---|
| Web | Superadmin, egasi, kassadagi sotuvchi | Hammasi |
| Android | Sotuvchi, egasi | Kamera skaneri, sotuv, nasiya, pasport rasmi, to'lov qabul qilish, Bluetooth chek printer |
| iOS | Do'kon egasi | Hisobotlar, qarzdorlar, qoldiq, kerak bo'lsa sotuv |
| Telegram bot | Egasi; mijoz | Egasiga kunlik savdo va foyda, muddati o'tganlar. Mijozga to'lov kunidan oldin eslatma (SMS yo'q). Mijoz botga "kontakt yuborish" orqali ulanadi. |
| Chek printer | Kassa | Termal 58/80 mm, ESC/POS. Web: brauzer print yoki QZ Tray. |
| Etiketka printer | Ombor | Code128 + IMEI + model + narx, TSPL/ZPL |

### Dizayn holati (Liquid Glass canvas)

| Ekran | Web | Android | iOS |
|---|---|---|---|
| Kassa / Sotuv | Bor | Bor | Bor |
| Nasiya shartnomasi | Bor | — | — |
| Nasiya ro'yxati, to'lov qabul qilish | Bor | Bor | Bor |
| Tovar kirimi | Bor | — | — |
| Ombor | Bor | Bor | Bor |
| Mijozlar | Bor | Bor | Bor |
| Kassalar, xarajat, bo'shatish | Bor | — | Hisobot ichida |
| Hisobotlar | Bor | — | Bor |
| Sozlamalar | Bor | — | — |
| **Superadmin paneli** | Yo'q | — | — |
| **Qaytarish** | Yo'q | — | — |
| **Filiallar orasida ko'chirish** | Yo'q | — | — |
| **Savat va to'lov (mobil)** | — | Yo'q | Yo'q |
| **Login** | Yo'q | Yo'q | Yo'q |

Qalin yozilganlar koddan oldin chizilishi kerak. Web'da sotuvchi ko'rinishida tannarx ustuni va Hisobotlar, Kassalar, Sozlamalar bo'limlari bo'lmaydi.

### Mobil texnologiyalar

| | Android | iOS |
|---|---|---|
| UI | Jetpack Compose | SwiftUI |
| Arxitektura | MVVM, Hilt, Coroutines/Flow | MVVM, async/await |
| Tarmoq | Retrofit + kotlinx.serialization | URLSession + Codable |
| Skaner | CameraX + ML Kit | VisionKit DataScanner |
| Token | Keystore | Keychain |
| Printer | Bluetooth ESC/POS | — |

---

## 6. Bosqichlar

Tartib texnik rejadagidek. 1–5 bosqichlar tugaganda bitta do'kon web orqali to'liq ishlaydi.

### 0. Tayyorgarlik
- Git, CI (test, Pint, Larastan), o'zbek tili fayllari.
- Baza tanlash va ulash; serverda root'ni `public/` ga o'tkazish, deploy skripti.
- Liquid Glass dizayn tokenlari (rang, radius, blur), umumiy layout.

### 1. Asos
- Multi-tenant: `tenant_id` + global scope, tenant izolyatsiya testlari.
- Superadmin paneli: do'kon ochish, egasi akkaunti, bloklash.
- Filiallar, xodimlar, rollar va ruxsatlar (Policy).
- Sozlamalar, kunlik kurs (Markaziy bankdan olish + qo'lda o'zgartirish).

**Natija:** superadmin do'kon ochadi, egasi kirib filial va sotuvchi yaratadi.

### 2. Ombor
- Kategoriya, kriteriyalar, mahsulot, variant.
- Kirimning 3 turi, ommaviy IMEI kiritish (skaner, Excel), IMEI tekshiruvi.
- Ichki barcode, etiketka chop etish.
- Aksesuar partiyalari (FIFO), filiallar orasida ko'chirish.
- Kassa hisoblari va harakatlar jurnali (mijozdan sotib olish kassaga bog'liq bo'lgani uchun shu yerda).

**Natija:** boshlang'ich qoldiq kiritilgan, har dona skaner bilan topiladi.

### 3. Sotuv (web POS)
- Skaner, savat, chegirma limiti, aralash valyuta va aralash to'lov.
- Savdoni yopish, chek, kafolat.
- Qaytarish (to'liq va qisman).

**Natija:** to'liq to'lovli savdo va qaytarish ishlaydi, kassa balansi to'g'ri.

### 4. Nasiya
- Mijoz kartasi, pasport rasmi (yopiq disk).
- Ustama jadvali, A va B rejim, yaxlitlash, shartnoma.
- To'lov qabul qilish (eng eski oylikdan taqsimlash), foyda ulushi.

**Natija:** nasiyaga sotish va to'lov yig'ish ishlaydi.

### 5. Kassa, xarajatlar, hisobotlar
- Kassalar ekrani, bo'shatish, xarajatlar.
- 8 ta hisobot: savdo va foyda, ombor qoldig'i, qarzdorlar, kassa, kutilayotgan foyda, sof foyda, kirim va yetkazib beruvchilar, kafolat va IMEI tarixi.
- Excel va PDF eksport.

**Natija:** birinchi ishlaydigan versiya — haqiqiy do'konda sinash mumkin.

### 6. API va Android
- `/api/v1`, Sanctum, idempotentlik, OpenAPI.
- Android: login, skaner, sotuv, nasiya, pasport rasmi, to'lov qabul qilish, Bluetooth printer.

### 7. Telegram bot
- Egasiga kunlik hisobot va muddati o'tganlar.
- Mijozni telefon raqami bilan bog'lash, to'lov eslatmasi.

### 8. iOS
- Egasi uchun: hisobotlar, qarzdorlar, qoldiq, sotuv.

### 9. Obuna billing
- Tariflar, limitlar, muddat nazorati, faqat o'qish rejimi.

---

## 7. Testlar

Nasiya jadvali, yaxlitlash va foyda ulushi uchun unit testlar 1-bosqichdanoq yoziladi. Texnik rejadagi misollar test holati sifatida olinadi:

| Holat | Kutilgan natija |
|---|---|
| A: 500 000, 3 oy, 20% | Jami 600 000; 200 000 × 3, birinchisi savdo kuni |
| B: 500 000, 100 000 boshlang'ich, 3 oy, 20% | Qarz 480 000; 160 000 × 3 |
| Yaxlitlash: 1 000 000 qarz, 3 oy | 340 000, 340 000, 320 000 |
| Foyda A: tushum 600 000, tannarx 450 000 | Ulush 25%; 200 000 → 50 000 |
| Foyda B: tushum 580 000, tannarx 450 000 | Ulush 22,41%; 100 000 → 22 414; 160 000 → 35 862 |
| Aralash savat: $460 × 12 750 + 60 000 | 5 925 000 so'm |
| Kutilayotgan foyda (A, 1 to'lovdan keyin) | 400 000 × 25% = 100 000 |

Qo'shimcha:
- tenant izolyatsiyasi — bir do'kon boshqasining ma'lumotini ko'ra olmaydi;
- sotuvchi javoblarida tannarx va foyda yo'qligi;
- bitta IMEI'ni ikki marta sotishga urinish;
- kassa qoldig'idan oshgan chiqimni rad etish; balans = harakatlar yig'indisi;
- FIFO: 10 × 20 000 va 10 × 25 000 partiyadan sotishda tannarx tartibi;
- qaytarishda foyda va kassaning teskari yozilishi.

---

## 8. Hal qilinmagan savollar

Texnik rejadagi barcha biznes savollari yopilgan. Qolganlari texnik:

| № | Savol | Tavsiya |
|---|---|---|
| 1 | MySQL yoki PostgreSQL? | PostgreSQL 16 — serverda bor, qator qulflash va decimal bilan ishlash ishonchli |
| 2 | Tenant qanday aniqlanadi: subdomen yoki bitta domen? | Bitta domen (`phonetrade.simulator.uz`), tenant foydalanuvchi akkauntidan — mobil ilova va SSL sodda bo'ladi |
| 3 | Mobil ilovalar oflayn ishlashi kerakmi? | Yo'q, faqat onlayn — IMEI va kassa ziddiyatlaridan qochish uchun |
| 4 | Android va iOS bir vaqtda yoki ketma-ket? | Ketma-ket: Android (6), keyin iOS (8) — texnik rejadagi tartib |
| 5 | Yetkazib beruvchiga qarz yuritiladimi? | Birinchi versiyada yo'q: to'lov qilinsa kassadan chiqim yoziladi, qarz hisobi yuritilmaydi |

---

## 9. Xavflar

| Xavf | Chora |
|---|---|
| Tenantlar orasida ma'lumot sizishi | Global scope + har model uchun izolyatsiya testi; superadmin so'rovlari alohida yo'lda |
| Sotuvchiga tannarx yoki foyda ko'rinib qolishi | Rolga qarab alohida API Resource; test bilan tekshirish |
| Valyuta va yaxlitlashda tiyin farqlari | Hisob faqat backendda, decimal, misollar test sifatida |
| Bir donani ikki marta sotish | Unique cheklov + tranzaksiya ichida qator qulflash |
| Kassa balansining jurnaldan farq qilishi | Faqat harakatlardan hisoblash, teskari yozuv bilan tuzatish |
| Tarmoq uzilganda takroriy to'lov | Idempotency-Key |
| Pasport ma'lumotlari sizishi | Yopiq disk, vaqtinchalik havola, audit |
| Server resurslari cheklangan (disk va xotira boshqa saytlar bilan bo'lishilgan) | Zaxira nusxalarni tashqi joyga olish, disk nazorati |
