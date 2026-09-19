# Savdodaftar Backend

Bozorchilar uchun "raqamli daftar" mobil ilovasining backend API'si. **Laravel 12 + MySQL + Sanctum.**

## Talablar

- PHP 8.2+
- Composer 2
- MySQL 8

## O'rnatish

```bash
composer install
cp .env.example .env
php artisan key:generate
# .env da DB_* ni to'ldiring
php artisan migrate
php artisan serve
```

Dev muhitda `SMS_DRIVER=log` bo'lsa OTP kodlar `storage/logs/laravel.log` ga yoziladi. Qulaylik uchun `OTP_DEBUG_CODE=123456` qo'ysangiz kod doimiy bo'ladi va `otp/send` javobida `debug_code` qaytadi (production'da ishlamaydi).

Production: `SMS_DRIVER=eskiz`, `ESKIZ_EMAIL`, `ESKIZ_PASSWORD`, `ESKIZ_FROM` (Eskiz'da tasdiqlangan sender name), `OTP_SMS_TEMPLATE` (tasdiqlangan shablon bilan mos).

Scheduler (eski OTP kodlarni tozalash): `* * * * * php artisan schedule:run`

## Testlar

```bash
php artisan test
```

## API

Base URL: `/api/v1`. Til: `Accept-Language: uz|ru`. Auth: `Authorization: Bearer <token>`.

Javob formati:

```json
{ "success": true, "message": "...", "data": { } }
{ "success": false, "message": "...", "code": "otp_invalid", "meta": { "attempts_left": 3 } }
```

### Auth

| Method | Endpoint | Tavsif |
|---|---|---|
| POST | `/auth/otp/send` | `{phone, purpose?: login\|reset_pin}` -> `{expires_in, resend_after}` |
| POST | `/auth/otp/verify` | `{phone, code, purpose?, device_name?}` -> `{token, is_new, user}` |
| GET | `/auth/me` | Joriy foydalanuvchi |
| PUT | `/auth/profile` | `{name, shop_name?, business_type?, locale?}` |
| PUT | `/auth/pin` | `{pin, current_pin?}` (mavjud PIN bo'lsa `current_pin` yoki `reset_pin` OTP talab qilinadi) |
| POST | `/auth/pin/verify` | `{pin}` |
| POST | `/auth/logout` | Joriy qurilma |
| POST | `/auth/logout-all` | Barcha qurilmalar |

Xatolik kodlari: `validation_failed`, `unauthenticated`, `too_many_requests`, `otp_too_soon`, `otp_daily_limit`, `otp_not_found`, `otp_expired`, `otp_blocked`, `otp_invalid`, `sms_send_failed`, `pin_not_set`, `pin_invalid`, `pin_blocked`, `pin_current_required`, `user_not_found`.

### Mijozlar

| Method | Endpoint | Tavsif |
|---|---|---|
| GET | `/customers` | `?search=&filter=all\|debtors\|clean&sort=name\|balance\|recent&per_page=` |
| POST | `/customers` | `{name, phone?, address?, note?, client_uuid?}` |
| GET | `/customers/{id}` | `open_debts_count`, `overdue_debts_count` bilan |
| PUT | `/customers/{id}` | Ma'lumotlarni yangilash |
| DELETE | `/customers/{id}` | Balansi 0 bo'lsa (`customer_has_debt`) |
| GET | `/customers/{id}/history` | Qarzlar + to'lovlar birlashtirilgan tarix (`items[]`) |
| POST | `/customers/{id}/payments` | `{amount, payment_method?, note?, paid_at?, client_uuid?}` — eng eski qarzlardan boshlab (FIFO) taqsimlanadi |

### Qarz daftari

| Method | Endpoint | Tavsif |
|---|---|---|
| GET | `/debts` | `?status=all\|unpaid\|open\|partial\|paid\|overdue&customer_id=&search=&sort=recent\|due_date` |
| GET | `/debts/summary` | `total_outstanding, debtors_count, overdue_count, overdue_amount, due_soon_count` |
| POST | `/debts` | `{customer_id, amount, note?, due_date? (Y-m-d), issued_at?, client_uuid?}` |
| GET | `/debts/{id}` | To'lovlar bilan |
| PUT | `/debts/{id}` | Faqat `due_date`, `note` |
| DELETE | `/debts/{id}` | To'lovi bo'lmasa (`debt_has_payments`) |
| POST | `/debts/{id}/payments` | `{amount, payment_method?: cash\|card\|other, note?, paid_at?, client_uuid?}` |
| GET | `/debts/{id}/audit` | O'zgarishlar tarixi (`created/updated/payment/deleted`, old/new qiymatlar) |

Qoidalar: qarz yozilganda mijoz `balance` oshadi, to'lovda kamayadi (tranzaksiya + row lock). To'lov qoldiqdan ko'p bo'lsa `payment_exceeds_debt`. `client_uuid` yuborilsa bir xil so'rov qayta kelganda yangi yozuv yaratilmaydi (offline sync uchun idempotentlik).

Xatolik kodlari: `customer_has_debt`, `payment_exceeds_debt`, `no_open_debts`, `debt_has_payments`, `not_found`.

### Ombor

| Method | Endpoint | Tavsif |
|---|---|---|
| GET | `/products` | `?search=&category=&filter=all\|low_stock\|out_of_stock\|attention\|inactive&sort=name\|recent\|stock\|price&per_page=` (`all` — faqat aktiv) |
| GET | `/products/summary` | `total_products, low_stock_count, out_of_stock_count, attention_count, stock_value, potential_revenue` |
| GET | `/products/categories` | `[{name, products_count}]` |
| GET | `/products/barcode/{barcode}` | Skaner uchun (`product_not_found`) |
| POST | `/products` | `{name, category?, barcode?, unit?, buy_price?, sell_price, stock? (boshlang'ich), min_stock?, is_active?, client_uuid?}` |
| GET | `/products/{id}` | Oxirgi 20 harakat bilan |
| PUT | `/products/{id}` | Ma'lumotlar va narxlar (qoldiq o'zgarmaydi) |
| DELETE | `/products/{id}` | Soft delete |
| POST | `/products/{id}/stock-in` | Kirim `{qty, buy_price?, update_buy_price?, note?, created_at?, client_uuid?}` |
| POST | `/products/{id}/stock-out` | Chiqim `{qty, note?, client_uuid?}` |
| POST | `/products/{id}/adjust` | Inventarizatsiya `{actual_stock, note?}` — farq `adjustment` harakati sifatida yoziladi |
| GET | `/products/{id}/movements` | `?type=&per_page=` |
| GET | `/products/{id}/audit` | Narx va ma'lumot o'zgarishlari tarixi |
| POST | `/products/{id}/image` | multipart `image` (max 4 MB) |
| DELETE | `/products/{id}/image` | Rasmni o'chirish |
| GET | `/inventory/movements` | `?type=&product_id=&from=Y-m-d&to=Y-m-d&per_page=` — barcha harakatlar |
| POST | `/inventory/count` | `{items: [{product_id, actual_stock}], note?}` — ko'p mahsulotli inventarizatsiya |

Birliklar (`unit`): `dona, kg, g, litr, metr, m2, qop, quti, pachka, juft, komplekt, boshqa`. Harakat turlari (`type`): `initial, in, out, sale, sale_return, purchase, purchase_return, adjustment`.

Qoidalar: qoldiq faqat `StockService` orqali o'zgaradi va har bir o'zgarish `stock_movements` ga yoziladi (row lock). `stock_status`: `ok | low | out` (`low` — `min_stock > 0` va `stock <= min_stock`). Chiqim qoldiqdan ko'p bo'lsa `insufficient_stock` (`INVENTORY_ALLOW_NEGATIVE_STOCK=true` bo'lsa ruxsat). Kirimda `buy_price` berilsa mahsulot tannarxi yangilanadi va audit tarixiga yoziladi. Rasmlar `public` diskda (`php artisan storage:link`).

Xatolik kodlari: `insufficient_stock`, `barcode_taken`, `product_not_found`.

### Savdo

| Method | Endpoint | Tavsif |
|---|---|---|
| GET | `/sales` | `?from=Y-m-d&to=Y-m-d&customer_id=&payment_method=&status=&search=&per_page=` (`items_count` bilan) |
| GET | `/sales/summary` | `?from=&to=` (default bugun): `sales_count, returns_count, total, returned_total, net_total, discount, profit, cash, card, debt, average_check` |
| POST | `/sales` | Savdoni yakunlash (pastda) |
| GET | `/sales/{id}` | `items`, `returns`, `debt`, `customer` bilan |
| POST | `/sales/{id}/return` | `{items?: [{sale_item_id, qty}], refund_method?: cash\|card\|debt, reason?, returned_at?, client_uuid?}` — `items` bo'sh bo'lsa to'liq qaytarish |
| GET | `/sales/returns` | `?from=&to=&per_page=` — barcha qaytarishlar |
| GET | `/sales/{id}/receipt` | Elektron chek: `lines[]`, `text` (ulashish/chop etish uchun) |
| GET | `/sales/{id}/audit` | `created / returned` tarixi |

`POST /sales` body:

```json
{
  "customer_id": 1,
  "payment_method": "cash | card | debt | mixed",
  "paid_cash": 100000, "paid_card": 40000, "debt_amount": 100000,
  "due_date": "2026-09-30",
  "discount": 0,
  "items": [
    { "product_id": 5, "qty": 2, "price": 120000 },
    { "name": "Paket", "qty": 3, "price": 1000 }
  ],
  "note": "...", "sold_at": "...", "client_uuid": "..."
}
```

Qoidalar:

- `items[]` — ombordagi mahsulot (`product_id`, `price` berilmasa `sell_price` olinadi) yoki tezkor mahsulot (`name` + `price`, omborga ta'sir qilmaydi).
- Har bir ombordagi mahsulot uchun `stock_movements` ga `sale` harakati yoziladi (`reference` = savdo); qoldiq yetmasa `insufficient_stock` va butun savdo bekor bo'ladi.
- `payment_method`: `cash`/`card`/`debt` — butun summa shu usulda; `mixed` — `paid_cash + paid_card + debt_amount = total` (`debt_amount` berilmasa qoldiq qarzga yoziladi). Qarz qismi > 0 bo'lsa `customer_id` shart (`customer_required`) va `debts` ga `sale_id` bilan qarz yoziladi (mijoz balansi oshadi).
- Foyda: `total_cost` = Σ qty × tannarx (savdo vaqtidagi), `profit = total − total_cost`. Qaytarishlar `returned_total/returned_cost` ga yig'iladi, `net_total` va `net_profit` shulardan hisoblanadi.
- Qaytarish: qoldiq `sale_return` harakati bilan tiklanadi, `status` → `partially_returned | returned`. `refund_method=debt` bo'lsa bog'langan qarz summasi kamayadi (qoldiqdan ko'p bo'lsa `refund_exceeds_debt`); berilmasa qarz ochiq va yetarli bo'lsa `debt`, aks holda `cash`/`card` tanlanadi.
- Mijoz tarixi (`/customers/{id}/history`) da savdolar `type: sale` sifatida chiqadi.

Xatolik kodlari: `payment_mismatch`, `customer_required`, `discount_exceeds_total`, `product_not_found`, `insufficient_stock`, `sale_already_returned`, `return_exceeds_sold`, `nothing_to_return`, `refund_exceeds_debt`.

### OTP xavfsizlik siyosati

- Kod 6 xonali, `bcrypt` hash bilan saqlanadi, TTL **120 s**.
- Noto'g'ri urinish: **5** marta, keyin yangi kod talab qilinadi.
- Bitta raqamga: **1 daqiqada 1 SMS**, kuniga **10** SMS; bitta IP dan kuniga 50.
- PIN: 5 noto'g'ri urinishdan keyin 5 daqiqa blok.
- PIN tiklash (`reset_pin`) barcha eski sessiyalarni bekor qiladi.
