# PhoneTrade — Android (sotuvchi ilovasi)

Kotlin + Jetpack Compose. Server bilan `docs/openapi.yaml` dagi `/api/v1` orqali ishlaydi;
pul hisob-kitobi (savat, chegirma, nasiya jadvali) faqat serverda bajariladi, ilova natijani ko'rsatadi.

## Ekranlar

- **Kirish** — telefon raqam va parol; token shifrlangan holda saqlanadi.
- **Sotuv** — kamera skaneri (IMEI / barcode) yoki nom bo'yicha qidiruv, savat, chegirma,
  to'lov (naqd / karta / o'tkazma, so'm / dollar), muddatli to'lov shartnomasi (mijoz kartasi,
  pasport rasmi), chek.
- **Nasiya** — bugungi, kechikkan va barcha shartnomalar; jadval; to'lov qabul qilish.
- **Ombor** — filialdagi telefonlar (tannarx faqat do'kon egasiga keladi).
- **Mijozlar** — ro'yxat va qidiruv.
- **Profil** — filialni almashtirish, Bluetooth chek printeri (ESC/POS, 58 yoki 80 mm), chiqish.

## Yig'ish

Android Studio'da `android/` papkasini oching yoki:

```bash
cd android
./gradlew assembleDebug        # app/build/outputs/apk/debug/app-debug.apk
./gradlew testDebugUnitTest    # API javoblari ilova modellariga mosligini tekshiradi
```

Ilova odatda `https://phonetrade.simulator.uz/api/v1/` ga ulanadi. Lokal serverda sinash uchun
(emulyatorda kompyuter `10.0.2.2` manzilida ko'rinadi):

```bash
php artisan serve --port=8000
cd android && ./gradlew installDebug -PapiUrl=http://10.0.2.2:8000/api/v1/
```

Shifrlanmagan (http) ulanish faqat debug yig'ilishda va faqat `10.0.2.2` / `localhost` uchun ruxsat etilgan.

## Release

`android/keystore.properties` faylini yarating (gitga qo'shilmaydi):

```properties
storeFile=/to/liq/yo'l/phonetrade.keystore
storePassword=...
keyAlias=phonetrade
keyPassword=...
```

So'ng `./gradlew bundleRelease`. Fayl bo'lmasa release imzosiz yig'iladi.

## Test ma'lumotlari

`app/src/test/resources/api/*.json` — lokal serverning (`migrate:fresh --seed`) haqiqiy javoblari.
API javobi o'zgarsa, shu fayllarni yangilab testni qayta ishga tushiring.
