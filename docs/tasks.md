# Bajarish rejasi

[plan.md](plan.md) nima qurilishini aytadi. Bu hujjat qanday tartibda bajarilishini aytadi: har bosqich alohida vazifalarga bo'lingan, har vazifa bitta pull request.

Jami 10 bosqich, 99 ta vazifa. 0–5 bosqichlar tugaganda bitta do'kon web orqali to'liq ishlaydi.

## Ish tartibi

- Har vazifa `main` dan alohida tarmoqda (`1.3-tenant-scope` ko'rinishida), pull request orqali qo'shiladi.
- PR qo'shilishi uchun: testlar, Pint va Larastan yashil.
- Pul hisoblaydigan har service uchun test avval yoziladi (plan.md, 7-bo'limdagi misollar).
- Har bosqich oxirida serverga deploy qilinadi va "Tayyor" shartlari qo'lda tekshiriladi.
- Vazifa bajarilgach shu fayldagi katakcha belgilanadi.
- Dizayni yo'q ekran avval Liquid Glass canvas'da chiziladi, tasdiqlangach kodlanadi.

## Qabul qilingan texnik qarorlar

plan.md, 8-bo'limdagi savollar bo'yicha tavsiyalar asos qilib olindi. Boshqacha bo'lishi kerak bo'lsa, 1-bosqichdan oldin ayting — keyin o'zgartirish qimmat.

| Savol                   | Qaror                                         |
| ----------------------- | --------------------------------------------- |
| Baza                    | PostgreSQL 16 (serverda ishlab turibdi)       |
| Tenant aniqlash         | Bitta domen, tenant foydalanuvchi akkauntidan |
| Mobil oflayn            | Yo'q, faqat onlayn                            |
| Mobil tartib            | Avval Android, keyin iOS                      |
| Yetkazib beruvchi qarzi | Birinchi versiyada yuritilmaydi               |
| Login                   | Telefon raqami + parol                        |
| Pul                     | `decimal(18,2)`, hisob bcmath bilan           |

## Sizdan kerak bo'ladigan narsalar

| Qachon    | Nima                                                                     |
| --------- | ------------------------------------------------------------------------ |
| 0-bosqich | GitHub repo'ni private qilish yoki ochiq qoldirish qarori                |
| 1-bosqich | Superadmin telefon raqami va paroli (o'zingiz kiritasiz)                 |
| 2-bosqich | Etiketka printeri modeli (TSPL yoki ZPL), yorliq o'lchami                |
| 3-bosqich | Chek printeri modeli va kengligi (58 yoki 80 mm); chekdagi rekvizitlar   |
| 5-bosqich | Sinov uchun haqiqiy do'kon va boshlang'ich qoldiq ro'yxati               |
| 6-bosqich | Google Play Console akkaunti; Android sinov qurilmasi; Bluetooth printer |
| 7-bosqich | Telegram bot tokeni (BotFather'dan)                                      |
| 8-bosqich | Apple Developer akkaunti; iPhone sinov qurilmasi                         |
| 9-bosqich | Tariflar: narx, filial va xodim limitlari, imtiyozli davr necha kun      |

---

## 0-bosqich — Tayyorgarlik

Maqsad: har o'zgarish tekshirilib, bitta buyruq bilan serverga chiqadi.

- [x] 0.1 Git repo, birinchi commit, GitHub'ga yuborish
- [x] 0.2 Serverda PHP 8.4, PostgreSQL bazasi, `public/` root, birinchi deploy
- [x] 0.3 Baza muhitlari: lokalda SQLite, CI va serverda PostgreSQL; `.env.example` yangilandi
- [x] 0.4 CI: GitHub Actions'da test, Pint, Larastan (starter kit workflow'larini tekshirish va tuzatish)
- [x] 0.5 Deploy: `scripts/deploy.sh` — lokal build, rsync, composer, migrate, kesh
- [x] 0.6 Ro'yxatdan o'tish, email tasdiqlash, parolni email orqali tiklash va passkey olib tashlandi; login telefon raqami bilan
- [x] 0.7 O'zbek tili: `lang/uz`, frontend tarjima fayllari, standart til `uz`
- [x] 0.8 Liquid Glass dizayn tokenlari (rang, radius, blur, shrift), asosiy komponentlar: tugma, maydon, segment, chip, jadval, panel
- [x] 0.9 Umumiy layout: yon menyu, sarlavha, kurs ko'rsatkichi; rolga qarab menyu
- [x] 0.10 Serverda cron: har daqiqada `schedule:run`; navbat schedule orqali ishlaydi
- [x] 0.11 Kunlik baza zaxira nusxasi (serverda, 14 kun saqlanadi; tashqi joyga nusxa hali yo'q)

**Tayyor:** `main` ga qo'shilgan o'zgarish bitta buyruq bilan saytga chiqadi; begona odam akkaunt ocha olmaydi.

## 1-bosqich — Asos

Maqsad: superadmin do'kon ochadi, egasi filial va sotuvchi yaratadi.

- [x] 1.1 `tenants` jadvali, `BelongsToTenant` trait, global scope, joriy tenant konteksti
- [x] 1.2 Tenant izolyatsiya testi: har model uchun avtomatik tekshiruv (bir do'kon boshqasini ko'rmaydi)
- [x] 1.3 Rollar: superadmin, egasi, sotuvchi; Policy'lar va middleware
- [x] 1.4 Superadmin paneli mavjud Liquid Glass uslubida qurildi (alohida canvas chizilmadi)
- [x] 1.5 Superadmin paneli: do'konlar ro'yxati, do'kon ochish, egasi akkaunti, bloklash
- [x] 1.6 Filiallar CRUD; filial yaratilganda 3 ta kassa va 3/6/9/12 oylik ustama qatorlari avtomatik
- [x] 1.7 Xodimlar CRUD, filiallarga biriktirish (`branch_user`), joriy filialni tanlash
- [x] 1.8 `Money` yordamchi tipi (valyuta, bcmath, formatlash) va testlari
- [x] 1.9 Kunlik kurs: `currency_rates`, Markaziy bankdan ertalab olish (schedule), egasi o'zgartirishi
- [x] 1.10 Sozlamalar ekrani: kurs, filiallar, xodimlar, ustama foizlari, yaxlitlash qadami, chek rekvizitlari
- [x] 1.11 `audit_logs` va yozish mexanizmi

**Tayyor:** ikkita sinov do'koni ochilgan, biri ikkinchisining hech narsasini ko'rmaydi; sotuvchi faqat o'z filialiga kiradi.

## 2-bosqich — Ombor

Maqsad: tovar kiritiladi, har dona skaner bilan topiladi.

- [x] 2.1 Kategoriyalar (serial / quantity), kriteriyalar va qiymatlari; Sozlamalardagi Kriteriyalar bo'limi
- [x] 2.2 Mahsulotlar va variantlar CRUD
- [x] 2.3 Kassa asosi: `cash_accounts`, `cash_movements`, `CashService` (kirim, chiqim, qoldiq tekshiruvi, teskari yozuv) + testlar
- [x] 2.4 `stock_units`, `stock_batches`; ichki barcode generatori (prefiks + tartib raqami)
- [x] 2.5 IMEI tekshiruvi (15 raqam, Luhn, tenant ichida unique)
- [x] 2.6 `ReceiveStock` service: yetkazib beruvchidan va boshlang'ich qoldiq
- [x] 2.7 `ReceiveStock`: mijozdan sotib olish — kassadan chiqim, qoldiq yetmasa rad etish
- [x] 2.8 Tovar kirimi ekrani: 3 tur, donalar jadvali, ketma-ket skanerlash, Excel'dan qo'yish
- [x] 2.9 Yetkazib beruvchilar CRUD
- [x] 2.10 Etiketka chop etish (Code128 + model + narx), brauzer orqali, 50×30 mm — printer modeliga moslash kerak
- [x] 2.11 Ombor ekrani: qidiruv, filtr, dona kartasi va tarixi, narx va kriteriyani o'zgartirish
- [x] 2.12 Sotuvchi uchun tannarxsiz ko'rinish (alohida Resource) + test
- [x] 2.13 Ko'chirish ekrani mavjud uslubda qurildi (alohida canvas chizilmadi)
- [x] 2.14 Filiallar orasida ko'chirish: jo'natish, "yo'lda", qabul qilish

**Tayyor:** 10 ta telefon bitta kirimda kiritilib, 10 ta etiketka chiqadi; mijozdan sotib olishda kassa kamayadi; sotuvchi tannarxni hech qayerda ko'rmaydi.

## 3-bosqich — Sotuv

Maqsad: to'liq to'lovli savdo va qaytarish boshidan oxirigacha ishlaydi.

- [ ] 3.1 `sales`, `sale_items`, `sale_payments` jadvallari
- [ ] 3.2 Savat hisobi service: chegirma limiti, aralash valyuta jami, qaytim + testlar
- [ ] 3.3 FIFO service: aksesuar tannarxini partiyalardan olish + testlar
- [ ] 3.4 `CloseSale` service: atomar tranzaksiya, dona qulflash, to'lovlarni kassaga yozish, foyda + testlar (ikki marta sotish, to'lov teng emas)
- [ ] 3.5 Mijozlar: jadval, qidiruv, tez qo'shish (to'liq karta 4-bosqichda)
- [ ] 3.6 Kassa (POS) ekrani: skaner maydoni, savat, chegirma, mijoz, 3×2 to'lov jadvali
- [ ] 3.7 Chek: 58/80 mm shablon, brauzer orqali chop etish
- [ ] 3.8 Kafolat: tugash sanasi, IMEI / barcode / telefon bo'yicha qidiruv
- [ ] 3.9 Qaytarish dizayni (canvas) → tasdiqlash
- [ ] 3.10 `ReturnSale` service: to'liq va qisman, kassadan chiqim, foyda va ombor teskari + testlar
- [ ] 3.11 Qaytarish ekrani va ruxsati (egasi yoki ruxsat berilgan xodim)

**Tayyor:** aralash valyutali savat aralash to'lov bilan yopiladi, chek chiqadi; qaytarishdan keyin kassa, ombor va foyda oldingi holatiga qaytadi.

## 4-bosqich — Nasiya

Maqsad: nasiyaga sotish va oylik to'lovlarni yig'ish ishlaydi.

- [ ] 4.1 Nasiya kalkulyatori service: A va B rejim, yaxlitlash, to'lov sanalari + testlar (plan.md misollari)
- [ ] 4.2 Foyda ulushi service: ulush, har to'lovdan foyda, oxirgi to'lovda tuzatish + testlar
- [ ] 4.3 `installment_plans`, `installment_schedule`, `installment_payments` jadvallari
- [ ] 4.4 Mijoz kartasi to'liq: pasport, JSHSHIR, manzil, qo'shimcha telefon
- [ ] 4.5 Pasport rasmlari: yopiq disk, vaqtinchalik havola, ruxsat tekshiruvi
- [ ] 4.6 `CloseSale` ni nasiya bilan kengaytirish: shartnoma va jadval yaratish, savdo kuni to'lovi
- [ ] 4.7 Shartnoma ekrani: jonli hisob (`preview`), jadval, tasdiqlash
- [ ] 4.8 `AcceptInstallmentPayment` service: eng eski oylikdan taqsimlash, ortiqcha summa, dollarli qarzni so'mda qabul qilish + testlar
- [ ] 4.9 Shartnomalar ro'yxati, filtrlar (bugun, kechikkan, faol, yopilgan), to'lov qabul qilish paneli
- [ ] 4.10 To'lov kvitansiyasi va shartnoma hujjati (chop etish)
- [ ] 4.11 Nasiyali savdoni qaytarish: shartnomani bekor qilish, to'langanini qaytarish
- [ ] 4.12 Mijozlar ekrani: karta, xaridlar va nasiya tarixi

**Tayyor:** ikkala rejimda shartnoma tuziladi, raqamlar plan.md misollari bilan tiyingacha mos; qisman va ortiqcha to'lov to'g'ri taqsimlanadi.

## 5-bosqich — Kassa, xarajatlar, hisobotlar

Maqsad: birinchi ishlaydigan versiya — haqiqiy do'konda sinash.

- [ ] 5.1 Kassalar ekrani: balanslar, jurnal, filtrlar
- [ ] 5.2 Kassa bo'shatish (filial → admin), admin kassa himoyasi + testlar
- [ ] 5.3 Xarajatlar: kategoriyalar, kiritish, kassadan chiqim
- [ ] 5.4 Hisobot: savdo va foyda (filial, sotuvchi, kategoriya, model kesimida)
- [ ] 5.5 Hisobot: ombor qoldig'i (tannarx, sotuv narxi, necha kun yotgani)
- [ ] 5.6 Hisobot: qarzdorlar; kutilayotgan foyda (oylar bo'yicha)
- [ ] 5.7 Hisobot: kassa; sof foyda
- [ ] 5.8 Hisobot: kirim va yetkazib beruvchilar; kafolat va IMEI tarixi
- [ ] 5.9 Hisobotlar ekrani: davr, filial, valyuta filtrlari; rolga qarab ustunlar
- [ ] 5.10 Excel va PDF eksport (navbat orqali)
- [ ] 5.11 Kunlik solishtirish buyrug'i: kassa balansi = harakatlar yig'indisi, ombor holati = harakatlar
- [ ] 5.12 Sinov do'konida to'liq sinov, topilgan xatolarni tuzatish

**Tayyor:** haqiqiy do'kon bir hafta web orqali ishlaydi; hisobot raqamlari qo'lda hisoblangan bilan mos.

## 6-bosqich — API va Android

Maqsad: sotuvchi telefon bilan sotadi.

- [ ] 6.1 `/api/v1` asosi: Sanctum, xato formati, `Idempotency-Key`, rate limit
- [ ] 6.2 Endpointlar: auth, kurs, ombor qidiruvi, mijozlar
- [ ] 6.3 Endpointlar: savdo (`preview`, yaratish, chek), nasiya (`preview`, bugungi to'lovlar, to'lov)
- [ ] 6.4 OpenAPI spetsifikatsiya va API testlari
- [ ] 6.5 Mobil login, savat va to'lov dizayni (canvas) → tasdiqlash
- [ ] 6.6 Android loyiha: Compose, Hilt, Retrofit, navigatsiya, Liquid Glass tema
- [ ] 6.7 Login, token saqlash, filial tanlash
- [ ] 6.8 Sotuv: kamera skaneri (CameraX + ML Kit), topilgan dona, savat, to'lov
- [ ] 6.9 Nasiya: shartnoma, bugungi va kechikkan to'lovlar, to'lov qabul qilish
- [ ] 6.10 Ombor va Mijozlar; pasportni kamera bilan olish
- [ ] 6.11 Bluetooth chek printer (ESC/POS)
- [ ] 6.12 Ichki test, Google Play'ga chiqarish

**Tayyor:** sotuvchi telefonda skanerlab sotadi va nasiya to'lovini qabul qiladi; raqamlar web bilan bir xil.

## 7-bosqich — Telegram bot

- [ ] 7.1 Bot webhook, buyruqlar asosi, navbat
- [ ] 7.2 Egasini bog'lash; kunlik savdo va foyda xabari; muddati o'tganlar ro'yxati
- [ ] 7.3 Mijozni "kontakt yuborish" orqali kartaga bog'lash
- [ ] 7.4 Mijozga to'lov eslatmasi (to'lov kunidan oldin) va qolgan qarz

**Tayyor:** egasi har kuni hisobot oladi; botga ulangan mijozga eslatma boradi.

## 8-bosqich — iOS

- [ ] 8.1 iOS loyiha: SwiftUI, tarmoq qatlami, Keychain, tema
- [ ] 8.2 Login va filial tanlash
- [ ] 8.3 Hisobot: ko'rsatkichlar, nasiya, kassalar, kassani bo'shatish
- [ ] 8.4 Nasiya va qarzdorlar, to'lov qabul qilish
- [ ] 8.5 Ombor va Mijozlar
- [ ] 8.6 Sotuv: skaner (VisionKit), savat, to'lov
- [ ] 8.7 TestFlight, App Store'ga chiqarish

**Tayyor:** egasi iPhone'da hisobot va qarzdorlarni ko'radi, kerak bo'lsa sotadi.

## 9-bosqich — Obuna

- [ ] 9.1 Tariflar: filial va xodim limitlari, narx
- [ ] 9.2 Obuna muddati, to'lovlar tarixi (superadmin kiritadi)
- [ ] 9.3 Limit nazorati: filial va xodim qo'shishda
- [ ] 9.4 Muddat tugashi: imtiyozli davr, keyin faqat o'qish rejimi + testlar
- [ ] 9.5 Superadmin statistikasi: barcha do'konlar bo'yicha

**Tayyor:** muddati o'tgan do'kon ma'lumotini ko'radi, lekin savdo qila olmaydi; to'lovdan keyin darhol ochiladi.

---

## Bog'liqliklar

```
0 ─▶ 1 ─▶ 2 ─▶ 3 ─▶ 4 ─▶ 5 ─┬─▶ 6 ─▶ 8
                            ├─▶ 7
                            └─▶ 9
```

- 2.3 (kassa asosi) 2.7 dan oldin bo'lishi shart: mijozdan sotib olish kassaga bog'liq.
- 4-bosqich 3.4 (`CloseSale`) ustiga quriladi.
- 6, 7 va 9-bosqichlar 5 dan keyin bir-biriga bog'liq emas — ustuvorlikka qarab tartibini almashtirish mumkin.
- 8 (iOS) 6.1–6.4 (API) ga bog'liq.
