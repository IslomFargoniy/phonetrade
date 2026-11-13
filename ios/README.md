# PhoneTrade — iOS (do'kon egasi ilovasi)

SwiftUI, iOS 17+. Server bilan `docs/openapi.yaml` dagi `/api/v1` orqali ishlaydi; pul hisob-kitobi
faqat serverda bajariladi.

## Ekranlar

- **Hisobot** (faqat egasi) — bugun / hafta / oy: sof foyda, tushum, xarajatlar, nasiya qarzi,
  kutilayotgan foyda; filial va admin kassalar; kassani bo'shatish.
- **Nasiya** — kechikkan, bugungi va barcha shartnomalar; jadval; to'lov qabul qilish.
- **Ombor** — filialdagi telefonlar, tannarxi bilan.
- **Mijozlar** — ro'yxat, qidiruv, qo'ng'iroq qilish.
- **Sotuv** — kamera skaneri (VisionKit) yoki qidiruv, savat, chegirma, to'liq to'lov.
  Muddatli to'lov shartnomasi (pasport rasmi bilan) Android ilova va web kassada rasmiylashtiriladi.

Token Keychain'da saqlanadi. Loyiha tashqi kutubxonalarsiz.

## Yig'ish

`ios/PhoneTrade.xcodeproj` ni Xcode'da oching (Xcode 16 yoki yangiroq) yoki:

```bash
cd ios
xcodebuild -project PhoneTrade.xcodeproj -scheme PhoneTrade \
  -destination 'platform=iOS Simulator,name=iPhone 18 Pro' -derivedDataPath build CODE_SIGNING_ALLOWED=NO build
```

Haqiqiy qurilma va TestFlight uchun Xcode'da _Signing & Capabilities_ bo'limida o'z Team'ingizni tanlang
(Apple Developer akkaunti kerak). Bundle ID: `uz.simulator.phonetrade`.

## Lokal serverda sinash (simulyator)

```bash
php artisan serve --port=8000
xcrun simctl install booted ios/build/Build/Products/Debug-iphonesimulator/PhoneTrade.app
SIMCTL_CHILD_PHONETRADE_API_URL=http://127.0.0.1:8000/api/v1/ \
SIMCTL_CHILD_PHONETRADE_TEST_LOGIN="telefon:parol" \
  xcrun simctl launch booted uz.simulator.phonetrade
```

`PHONETRADE_TEST_LOGIN` faqat debug yig'ilishda ishlaydi (avtomatik kirish). Shifrlanmagan (http)
ulanish faqat lokal tarmoq uchun ruxsat etilgan.
