# BOZORCHILAR UCHUN MOBIL ILOVA

**To'liq UI/UX + Texnik topshiriq (TZ) — v3**

**Maqsad:** qog'oz daftarni to'liq raqamlashtirish — qarz, savdo, mijoz, ombor, xarid, xarajat, hisobot va biznes nazoratini bitta sodda ilovada boshqarish.

**Asosiy prinsip:** bozorchiga murakkab buxgalteriya emas, 2–3 bosishda ishini bitirish imkonini berish.

**Versiya:** 1.0 | **Platforma:** Android / iOS | **Til:** O'zbek tili, Rus tili

---

## 1. Mahsulot konsepsiyasi

Ilova bozorchilar, kichik do'kon egalari va savdogarlar uchun "raqamli daftar" vazifasini bajaradi. Foydalanuvchi qog'oz daftar o'rniga telefonda mijozlar, qarzlar, savdo, mahsulot qoldig'i, xarajat va foydani yuritadi.

- Harakatlar maksimal sodda: katta tugmalar, kam forma, avtomatik hisob-kitob.
- Internet bo'lmasa ham asosiy ma'lumotlarni ko'rish va kiritish; internet qaytganda sinxronlash.
- Qarz va savdo tarixi o'chib ketmasligi uchun avtomatik backup.
- Ilova professional ko'rinsa ham, birinchi marta smartfon ishlatayotgan foydalanuvchi ham tushunadigan UX.

## 2. MVP funksiyalar — birinchi versiya

| Modul | Asosiy funksiyalar |
|---|---|
| Kirish | Telefon raqami, SMS/OTP, PIN |
| Bosh sahifa | Bugungi savdo, foyda, qarz, qaytarilgan qarz, tezkor tugmalar |
| Mijozlar | Qo'shish, qidirish, telefon, balans, tarix |
| Qarz daftari | Qarz berish, to'lov qabul qilish, qoldiq, muddat |
| Savdo | Mahsulot, miqdor, narx, naqd/karta/qarz |
| Ombor | Mahsulot, tannarx, sotuv narxi, qoldiq, kam qoldiq |
| Xarajatlar | Ijara, transport, maosh, reklama, elektr va boshqalar |
| Hisobot | Kunlik/haftalik/oylik savdo, xarajat, foyda |
| Bildirishnomalar | Muddati o'tgan qarz, kam qolgan mahsulot |
| Backup | Bulutga zaxira nusxa va tiklash |

## 3. Splash va Onboarding

- **Splash:** logo + "Daftaringiz endi telefoningizda."
- **Onboarding 1:** "Qog'oz daftarni unuting" — qarz va mijozlar telefoningizda.
- **Onboarding 2:** "Savdoni hisoblang" — har bir savdoni tez yozing.
- **Onboarding 3:** "Biznesingizni nazorat qiling" — foyda, qarz va omborni ko'ring.
- **CTA:** "Boshlash"

## 4. Ro'yxatdan o'tish / Login

Ro'yxatdan o'tish telefon raqami orqali amalga oshiriladi. Tasdiqlash SMS kodi backend API orqali Eskiz.uz xizmati yordamida yuboriladi.

- Telefon raqami (E.164 format: +998901234567) → Eskiz.uz orqali SMS/OTP yuboriladi → foydalanuvchi kodni kiritadi → ism → savdo turi → do'kon/bozor nomi.
- OTP kod backend'da hash qilingan holda (yoki TTL bilan Redis'da) saqlanadi; amal qilish muddati 60–120 soniya.
- Noto'g'ri urinishlar soni cheklanadi (3–5 marta), undan keyin yangi kod so'rash talab qilinadi.
- Bitta telefon raqamiga SMS yuborish chastotasi cheklanadi (masalan, 1 daqiqada 1 marta, kuniga cheklangan son) — SMS-bombing hujumlaridan himoya va Eskiz xarajatini nazorat qilish uchun.
- "SMS kelmadimi? Qayta yuborish" tugmasi 30–45 soniyadan keyin faollashadi.
- Muvaffaqiyatli ro'yxatdan o'tgandan keyin PIN kod yaratiladi; keyingi kirishlarda faqat PIN/biometrika so'raladi — SMS faqat birinchi ro'yxatdan o'tishda va parolni tiklashda ishlatiladi.
- Qurilma qo'llasa biometrik himoya qo'shiladi.
- Keyinchalik Google/Apple orqali kirish qo'shilishi mumkin.

## 5. Bosh sahifa — Dashboard

- Yuqori qism: profil/do'kon nomi + bildirishnoma.
- Kartalar: Bugungi savdo | Bugungi foyda | Berilgan qarz | Qaytgan qarz.
- 4 ta katta Quick Action: + Savdo | + Qarz | + To'lov | + Kirim.
- Ogohlantirishlar: "3 ta qarz muddati o'tgan", "5 ta mahsulot kam qoldi".
- "Bugun nima bo'ldi?" blokida kunlik qisqa xulosa.
- Pastki navigatsiya: Bosh sahifa | Mijozlar | Qarzlar | Ombor | Sozlamalar.
- Pro versiyani yoqish taklifi bildirishnoma blokidan oldin alohida bannerda ko'rsatiladi (bat. 36-bo'lim).

## 6. Savdo moduli

- Mahsulot qidirish → mahsulotni tanlash → miqdor → savat → to'lov turi.
- To'lov: Naqd | Karta | Qarz | Aralash to'lov.
- Savdoni yakunlash → chek → tarixga saqlash.
- Barcode orqali mahsulotni topish.
- Mahsulot topilmasa: "Tezkor mahsulot qo'shish".
- Qaytarish: savdoni bekor qilish/qaytarish va omborni avtomatik tiklash.

## 7. Qarz daftari

- Mijoz → summa → sabab/mahsulot → sana → qaytarish muddati → saqlash.
- Qisman to'lovni qabul qilish.
- Qoldiq qarz avtomatik hisoblanadi.
- Qarz tarixi: qachon berildi, qancha qaytdi, qancha qoldi.
- Muddati o'tgan qarzlar alohida filtrda.
- Qarz eslatmasini Telegram/WhatsApp orqali yuborish — V2 integratsiya.
- **Muhim:** qarz bo'yicha har bir o'zgarish audit tarixida saqlanadi.

## 8. Ovoz orqali boshqarish — Killer Feature

- "Ali akaga 150 ming qarz yoz." → Ali +150 000.
- "Ali 100 ming qarzini berdi." → balans 100 000 ga kamayadi.
- "Futbolka 10 dona kirim qil." → ombor +10.
- "Bugungi savdoni ko'rsat." → dashboard hisoboti.
- Ovozdan keyin ilova bajariladigan amalni ko'rsatib, kerak bo'lsa "Tasdiqlash" so'raydi.

**UX:** Mikrofon tugmasi bosh sahifada doim ko'rinadigan bo'lishi mumkin.

## 9. Mijozlar

- Ism, telefon, manzil, izoh.
- Jami qarz / balans.
- + Qarz | To'lov | Eslatma | Tarix.
- Mijozni qidirish va guruhlash: qarzdorlar, faol mijozlar, ko'p xarid qiluvchilar.
- "Eng yaxshi mijozlar" analitikasi.

## 10. Mijoz profili

- Profil sarlavhasi: ism + telefon + balans.
- Asosiy tugmalar: +Qarz | To'lov | Eslatma.
- Tarix: savdolar, qarzlar, to'lovlar, qaytarishlar.
- Mijozga tayyor qarz eslatmasini yuborish.

## 11. Ombor

- Mahsulot nomi, kategoriya, birlik, tannarx, sotuv narxi, qoldiq.
- Kam qoldiq chegarasi va avtomatik ogohlantirish.
- Barcode skanerlash.
- Kirim, chiqim, inventarizatsiya, qaytarish.
- Qidirish va filtr: kategoriya, kam qoldiq, ko'p sotilgan, foydasi yuqori.

## 12. Mahsulot qo'shish

- Nomi → rasm → barcode → birlik → tannarx → sotuv narxi → boshlang'ich qoldiq → minimal qoldiq.
- Avtomatik marja va taxminiy foydani ko'rsatish.

## 13. Xaridlar va yetkazib beruvchilar

- Yetkazib beruvchi profili: nomi, telefon, manzil.
- Xarid tarixi va yetkazib beruvchiga qarz.
- Tovar kirimi: mahsulot + miqdor + tannarx.
- Qisman to'lov va qolgan majburiyat.
- Yetkazib beruvchi bo'yicha umumiy qarzdorlik.

## 14. Xarajatlar

- Kategoriya: ijara, transport, maosh, reklama, elektr, internet, boshqa.
- Summa, sana, izoh.
- Kun/hafta/oy bo'yicha xarajatlar.
- Sof foyda = savdo daromadi − sotilgan mahsulot tannarxi − xarajatlar.

## 15. Eski daftarni ko'chirish — Killer Feature

- Foydalanuvchi eski qog'oz daftarni kameraga oladi.
- OCR/AI yozuvni tahlil qilib, mijozlar va qarz summalarini ajratadi.
- Importdan oldin foydalanuvchiga topilgan ma'lumotlarni tekshirtiradi.
- Tasdiqlangandan keyin mijozlar va qarzlar ilovaga qo'shiladi.
- Noaniq qo'l yozuvi uchun "Tekshirish kerak" belgisi qo'yiladi.

**Maqsad:** bozorchining eng katta to'sig'i — eski daftar ma'lumotlarini qayta kiritish muammosini kamaytirish.

## 16. Chek va kvitansiya

- Savdo tugagach elektron chek/kvitansiya.
- Mijozga ulashish.
- Keyinchalik Bluetooth printer orqali kichik qog'oz chek chiqarish.
- Chekda: mahsulotlar, miqdor, jami, to'lov turi, sana.

## 17. Hisobot va analitika

| Hisobot | Ko'rsatiladigan ma'lumot |
|---|---|
| Bugun | Savdo, xarajat, foyda, qarz, qaytgan qarz, naqd/karta |
| 7 kun | Kunlar bo'yicha savdo va foyda |
| 30 kun | Savdo, foyda, xarajat, qarz dinamikasi |
| Oy | Eng ko'p sotilgan mahsulotlar, foydali mahsulotlar |
| Mahsulot | Sotilgan dona, tushum, tannarx, foyda |
| Mijoz | Xaridlar va qarz tarixi |
| Ombor | Qoldiq va taxminiy ombor qiymati |

- Custom date: istalgan ikki sana oralig'ini tanlash.
- Excel/PDF eksport — keyingi bosqich.

## 18. "Bugun nima bo'ldi?"

- Ilova kun oxirida avtomatik xulosa beradi: "Bugun 3 450 000 so'mlik savdo, 620 000 so'm yalpi foyda, 180 000 so'm xarajat qilindi."
- Shuningdek: berilgan qarz, qaytgan qarz, kam qolgan mahsulotlar.
- Bu blok foydalanuvchiga har kuni ilovani ochish odatini yaratishga yordam beradi.

## 19. Mahsulot analitikasi

- Eng ko'p sotilgan mahsulot.
- Eng ko'p foyda bergan mahsulot.
- Kam sotilayotgan mahsulot.
- Qoldig'i tez tugayotgan mahsulot.
- Tannarx va sotuv narxi o'zgarishi tarixi.

---

## 20. Qaytarishlar

- Savdoni topish → qaytariladigan mahsulot/miqdor → sabab → tasdiqlash.
- Ombor qoldig'i avtomatik tiklanadi.
- Hisobotdagi tushum va foyda qayta hisoblanadi.

## 21. Inventarizatsiya

- Ilovadagi qoldiq va real qoldiqni solishtirish.
- Kamomad/ortiqcha avtomatik hisoblanadi.
- Inventarizatsiya yakunlanganda tuzatishlar tarixga yoziladi.

## 22. Bildirishnomalar

- Qarz muddati yaqinlashdi.
- Qarz muddati o'tdi.
- Mahsulot minimal qoldiqdan past.
- Backup bajarildi/bajarilmadi.
- Kunlik hisobot tayyor.
- Pro obuna muddati tugashiga 2–3 kun qolganda eslatma (agar Pro faol bo'lsa).

## 23. Xavfsizlik

- PIN/biometrik himoya.
- Sessiyani avtomatik bloklash.
- Bulut backup.
- Ma'lumotlarni o'chirishdan oldin tasdiqlash.
- Muhim moliyaviy amallar uchun tasdiqlash oynasi.

## 24. AI biznes yordamchi (Pro)

- "Bugun qancha foyda qildim?"
- "Kimlarning qarzi muddati o'tgan?"
- "Qaysi mahsulot ko'p sotildi?"
- "Qaysi mahsulot eng ko'p foyda berdi?"
- "Bu oy o'tgan oyga nisbatan savdo qanday?"
- "10 dona kam qolgan mahsulotlarni ko'rsat."
- AI faqat o'qish emas, tasdiqdan keyin amal bajarishi mumkin: qarz yozish, mahsulot qo'shish, hisobot tayyorlash.

## 25. Xodimlar va rollar — V2

- Admin/egasi.
- Sotuvchi.
- Omborchi.
- Har bir rolga alohida ruxsat.
- Xodimlar qilgan savdo va o'zgarishlar tarixi.

## 26. Navigatsiya

- Bottom Navigation: Bosh sahifa | Mijozlar | Qarzlar | Ombor | Sozlamalar
- Floating/Quick action: + Savdo, + Qarz, + To'lov, + Kirim.
- Global: qidiruv, mikrofon, bildirishnoma.

## 27. Asosiy user flow'lar

| Flow | Ketma-ketlik |
|---|---|
| Savdo | Home → +Savdo → Mahsulot → Miqdor → To'lov → Chek → Tarix |
| Qarz | Home → +Qarz → Mijoz → Summa → Muddat → Saqlash |
| To'lov | Mijoz/Qarz → To'lov → Summa → Usul → Tasdiqlash |
| Kirim | Ombor → +Kirim → Mahsulot → Miqdor → Tannarx → Saqlash |
| Ovoz | Mikrofon → Ovoz → AI tahlil → Tasdiqlash → Amal |
| Eski daftar | Skaner → OCR/AI → Tekshirish → Import → Tayyor |
| Qaytarish | Savdo tarixi → Savdo → Qaytarish → Miqdor → Tasdiqlash |
| Ro'yxatdan o'tish | Telefon → Eskiz SMS/OTP → Kod tasdiqlash → Ism/Do'kon → PIN |
| Pro faollashtirish | Sozlamalar/Bosh sahifa → Pro banner → Checkout → Payme/Click → Webhook → Pro faol |

## 28. Data model — asosiy jadvallar

- **users**: id, name, phone, pin, shop_name, created_at
- **customers**: id, user_id, name, phone, address, note, balance
- **debts**: id, customer_id, amount, paid_amount, due_date, status, note
- **debt_payments**: id, debt_id, amount, payment_method, created_at
- **products**: id, user_id, name, barcode, unit, buy_price, sell_price, stock, min_stock
- **sales**: id, user_id, customer_id, total, payment_method, created_at
- **sale_items**: id, sale_id, product_id, qty, price, buy_price
- **purchases**: id, supplier_id, total, paid_amount, created_at
- **purchase_items**: id, purchase_id, product_id, qty, buy_price
- **expenses**: id, user_id, category, amount, note, created_at
- **suppliers**: id, user_id, name, phone, address, balance
- **stock_movements**: id, product_id, type, qty, reference_id, created_at
- **otp_codes**: id, phone, code_hash, attempts, expires_at, created_at *(yangi)*
- **subscriptions**: id, user_id, plan (free/pro), status, started_at, expires_at *(yangi)*
- **payments**: id, user_id, subscription_id, amount, provider (payme/click), order_id, transaction_id, status, created_at *(yangi)*
- **notifications, employees, audit_logs, backups** — V2.

## 29. UX qoidalari

- Har bir asosiy amal 2–3 bosqichdan oshmasin.
- Katta tugmalar va o'qilishi oson shrift.
- Pul summalari doim aniq formatda: 150 000 so'm.
- Xatoliklar oddiy tilda: "Miqdor 0 bo'lishi mumkin emas."
- Saqlashdan oldin moliyaviy amal uchun qisqa preview.
- Offline holatda kiritilgan ma'lumotlar keyin sinxronlansin.
- Muhim amallarda Undo imkoniyati bo'lsin.

## 30. MVP / V2 / V3 roadmap

| Bosqich | Funksiyalar |
|---|---|
| MVP | Login (telefon+Eskiz SMS), Home, Mijozlar, Qarz, To'lov, Savdo, Ombor, Xarajat, Hisobot, Bildirishnoma, Backup, Free/Pro tarif va Payme/Click orqali Pro faollashtirish |
| V2 | Ovozli boshqaruv, barcode, qaytarish, inventarizatsiya, yetkazib beruvchilar, xaridlar, chek, Telegram/WhatsApp eslatmalar |
| V3 | Eski daftar OCR/AI, AI biznes yordamchi, xodimlar, printer, Excel/PDF eksport, chuqur analitika |

## 31. Monetizatsiya

Ilovada ikkita tarif mavjud: Free va Pro.

- **Free**: asosiy mijoz, qarz, savdo va cheklangan hisobot.
- **Pro**: AI'ga oid barcha xizmatlar (ovozli boshqaruv, AI biznes yordamchi, eski daftar OCR/AI import) + cheksiz mijoz/mahsulot, backup, eksport, rivojlangan analitika.
- **Pro narxi**: 49 000 so'm.
- Pro'ni yoqish taklifi uchta joyda ko'rsatiladi: bosh sahifada, bildirishnoma blokidan oldin, va Sozlamalar bo'limida.
- Faollashtirish backend API orqali to'liq checkout oqimi bilan amalga oshiriladi: foydalanuvchi Payme yoki Click to'lov sahifasiga yo'naltiriladi, to'lov muvaffaqiyatli bo'lgach backend webhook orqali tasdiqlaydi va Pro tarifni faollashtiradi.

## 32. Eng kuchli differensial funksiyalar

| Funksiya | Foydalanuvchiga qiymati |
|---|---|
| Raqamli daftar | Qog'oz daftarni almashtiradi |
| Ovozli qarz/savdo | Yozmasdan tez ishlash |
| Eski daftar OCR | Eski ma'lumotlarni qo'lda ko'chirishni kamaytiradi |
| Barcode | Savdoni tezlashtiradi |
| AI yordamchi | Hisobotni oddiy savol bilan olish |
| Avtomatik foyda | Haqiqiy natijani tushunish |
| Qarz eslatmasi | Qarz yig'ishni tizimlashtirish |

## 33. Yakuniy mahsulot pozitsiyasi

**"Bozorchi uchun telefon ichidagi aqlli daftar."**

Ilovaning asosiy vazifasi ko'p funksiyali bo'lish emas, balki bozorchining kundalik ishini qog'oz daftaridan telefonga imkon qadar oson ko'chirishdir. Shuning uchun MVP'da qarz + mijoz + savdo + ombor + xarajat + hisobot birinchi o'rinda turadi; ovoz, OCR va AI esa mahsulotni kuchli differensial qiladi.

## 34. Developer uchun qisqa acceptance checklist

- Yangi foydalanuvchi 5 daqiqada ro'yxatdan o'tib, birinchi mijoz va qarzni qo'sha oladi.
- Savdo 3–5 ta asosiy bosishda yakunlanadi.
- Qarz qoldig'i har bir to'lovdan keyin avtomatik yangilanadi.
- Savdo ombor qoldig'ini avtomatik kamaytiradi.
- Qaytarish ombor qoldig'ini va hisobotlarni qayta hisoblaydi.
- Tannarx mavjud bo'lsa foyda avtomatik hisoblanadi.
- Xarajatlar sof foydaga ta'sir qiladi.
- Kam qoldiq va muddati o'tgan qarzlar ko'rinadi.
- Backup va tiklash ishlaydi.
- Ovozli buyruqlar xavfsiz tasdiqlashdan keyin bajariladi.
- OCR import qilingan ma'lumotni foydalanuvchi tasdiqlamasdan avtomatik moliyaviy amal sifatida qabul qilmaydi.
- OTP kod 60–120 soniya amal qiladi, 3–5 marta noto'g'ri urinishdan keyin bloklanadi.
- Bitta telefon raqamiga SMS yuborish chastotasi cheklangan (rate limiting ishlaydi).
- Pro checkout: order_id yaratiladi, Payme/Click'ga uzatiladi, webhook orqali status backend'da tasdiqlanadi (foydalanuvchi ilovaga qaytmasa ham).
- Pro faollashtirilgach AI funksiyalari (ovoz, AI yordamchi, OCR import) darhol ochiladi; Free foydalanuvchiga bu funksiyalar ko'rinadi, lekin bosilganda Pro taklif banneriga yo'naltiriladi.

## 35. Tariflar — Free va Pro (batafsil)

### 35.1 Free tarif

- Mijozlar, qarz daftari, savdo, ombor va xarajat modullariga to'liq kirish.
- Cheklangan hisobot (Bugun, 7 kun).
- Ovozli boshqaruv, AI yordamchi va eski daftar OCR import — yopiq, Pro taklif banneri ko'rsatiladi.

### 35.2 Pro tarif — 49 000 so'm

- Ovozli boshqaruv (savdo/qarz/kirim ovoz orqali).
- AI biznes yordamchi (savol-javob, hisobot tayyorlash).
- Eski daftar OCR/AI import.
- Kengaytirilgan hisobot (30 kun, Custom date, Excel/PDF eksport).
- Cheksiz mijoz/mahsulot soni, bulutga backup.

### 35.3 Pro taklif joylashuvi

- Bosh sahifada — dashboard yuqori qismida banner.
- Bildirishnoma blokidan oldin — alohida ajratilgan banner.
- Sozlamalar bo'limida — "Pro'ga o'tish" alohida qatorda, joriy tarif holati bilan.

## 36. Pro faollashtirish — to'lov oqimi (Payme / Click)

Pro tarifni faollashtirish to'liq backend API orqali, checkout sahifasiga o'tish va Payme yoki Click orqali to'lovni amalga oshirish yo'li bilan bajariladi.

### 36.1 Bosqichma-bosqich oqim

1. Foydalanuvchi bosh sahifa / bildirishnoma / sozlamalardagi Pro bannerini bosadi.
2. Backend'da noyob order_id bilan yangi to'lov yozuvi (status: pending) yaratiladi.
3. Foydalanuvchi Payme yoki Click'ni tanlaydi va tegishli checkout sahifasiga order_id bilan yo'naltiriladi.
4. Foydalanuvchi checkout sahifasida to'lovni amalga oshiradi (karta orqali).
5. Payme/Click backend'ga webhook (callback) yuboradi — bu asosiy tasdiqlash manbai, foydalanuvchi ilovaga qaytishiga bog'liq emas.
6. Backend webhook'ni tekshiradi (imzo/tasdiqlash), to'lov yozuvini status: paid ga o'zgartiradi va subscriptions jadvalida Pro'ni faollashtiradi (expires_at belgilanadi, agar obuna muddatli bo'lsa).
7. Foydalanuvchi ilovaga qaytganda joriy holatni so'raydi (polling) yoki push-bildirishnoma orqali "Pro faollashtirildi" xabarini oladi.

### 36.2 Xatolik va chekka holatlar

- To'lov jarayonida internet uzilsa yoki ilova yopilsa — "To'lov kutilmoqda" oraliq holati ko'rsatiladi, ikki marta to'lovni oldini olish uchun order_id status tekshiriladi.
- Webhook kelmasa (tarmoq nosozligi) — backend Payme/Click API orqali status-check so'rovini qayta yuboradi (retry mexanizmi).
- To'lov muvaffaqiyatsiz bo'lsa — foydalanuvchiga sabab ko'rsatilib, qayta urinish imkoniyati beriladi.
- Bir xil order_id bo'yicha ikki marta webhook kelsa — backend idempotent tarzda ishlaydi (takroriy faollashtirmaydi).

### 36.3 Texnik talablar

- **payments** jadvali: id, user_id, subscription_id, amount, provider (payme/click), order_id, transaction_id, status (pending/paid/failed), created_at.
- **subscriptions** jadvali: id, user_id, plan, status, started_at, expires_at.
- Payme va Click uchun alohida merchant sertifikatlash va integratsiya — development jadvalida alohida vazifa sifatida rejalashtiriladi.
- Obuna muddati tugashiga 2–3 kun qolganda foydalanuvchiga bildirishnoma yuboriladi (avtomatik uzaytirish yo'q, agar boshqacha qaror qilinmasa).

## 37. Ro'yxatdan o'tish — telefon raqami va SMS tasdiqlash (Eskiz.uz)

### 37.1 Oqim

- Foydalanuvchi telefon raqamini kiritadi (E.164 format: +998XXXXXXXXX).
- Backend API Eskiz.uz orqali bir martalik SMS kod (OTP) yuboradi.
- Foydalanuvchi kodni kiritadi → backend tasdiqlaydi → ism, savdo turi, do'kon/bozor nomi so'raladi → PIN kod o'rnatiladi.
- Keyingi kirishlarda faqat PIN yoki biometrika so'raladi; SMS faqat birinchi ro'yxatdan o'tish va parol/PIN tiklashda ishlatiladi.

### 37.2 Xavfsizlik va cheklovlar

- OTP kod backend'da hash qilingan holda yoki TTL bilan tezkor xotirada (masalan Redis) saqlanadi.
- Kod amal qilish muddati: 60–120 soniya.
- Noto'g'ri urinishlar soni cheklanadi (3–5 marta), keyin yangi kod talab qilinadi.
- Bitta raqamga SMS yuborish chastotasi cheklanadi (masalan, 1 daqiqada 1 marta, kunlik limit) — SMS-bombing hujumidan himoya va Eskiz xarajatlarini nazorat qilish uchun.
- "Qayta yuborish" tugmasi 30–45 soniyadan keyin faollashadi.

### 37.3 Eskiz.uz integratsiyasi bo'yicha eslatmalar

- Eskiz'da SMS jo'natuvchi nom (sender name) oldindan ro'yxatdan o'tkazilishi kerak — development boshlanishidan oldin ariza topshirish tavsiya etiladi.
- Eskiz API'ning kunlik/oylik limiti va narxi loyihaning kutilayotgan foydalanuvchi soniga qarab oldindan hisoblab chiqilishi kerak.

