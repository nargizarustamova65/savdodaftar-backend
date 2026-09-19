<?php

return [
    'customer' => [
        'created' => 'Mijoz qo\'shildi.',
        'updated' => 'Mijoz ma\'lumotlari saqlandi.',
        'deleted' => 'Mijoz o\'chirildi.',
        'has_debt' => 'Qarzi bo\'lgan mijozni o\'chirib bo\'lmaydi. Avval qarzni yoping.',
    ],

    'debt' => [
        'created' => 'Qarz yozildi.',
        'updated' => 'Qarz ma\'lumotlari saqlandi.',
        'deleted' => 'Qarz o\'chirildi.',
        'payment_recorded' => 'To\'lov qabul qilindi. Qolgan qarz: :remaining.',
        'payment_exceeds' => 'To\'lov summasi qarzdan ko\'p bo\'lishi mumkin emas. Qolgan qarz: :remaining.',
        'no_open_debts' => 'Bu mijozda ochiq qarz yo\'q.',
        'has_payments' => 'To\'lovi bo\'lgan qarzni o\'chirib bo\'lmaydi.',
    ],

    'product' => [
        'created' => 'Mahsulot qo\'shildi.',
        'updated' => 'Mahsulot ma\'lumotlari saqlandi.',
        'deleted' => 'Mahsulot o\'chirildi.',
        'barcode_taken' => 'Bu barcode ":name" mahsulotiga biriktirilgan.',
        'not_found_by_barcode' => 'Bu barcode bo\'yicha mahsulot topilmadi.',
        'not_found' => 'Mahsulot topilmadi.',
        'image_saved' => 'Rasm saqlandi.',
        'image_deleted' => 'Rasm o\'chirildi.',
    ],

    'stock' => [
        'initial_note' => 'Boshlang\'ich qoldiq',
        'in' => 'Kirim saqlandi. Yangi qoldiq: :stock.',
        'out' => 'Chiqim saqlandi. Yangi qoldiq: :stock.',
        'adjusted' => 'Qoldiq tuzatildi. Farq: :diff.',
        'no_change' => 'Qoldiq o\'zgarmadi.',
        'insufficient' => '":product" omborda yetarli emas. Mavjud: :available.',
        'inventory_saved' => 'Inventarizatsiya yakunlandi. :count ta mahsulot qoldig\'i tuzatildi.',
    ],

    'sale' => [
        'created' => 'Savdo yakunlandi. Jami: :total.',
        'returned' => 'Qaytarish saqlandi. Summa: :total.',
        'discount_exceeds' => 'Chegirma savdo summasidan ko\'p bo\'lishi mumkin emas.',
        'payment_mismatch' => 'To\'lovlar yig\'indisi (:paid) savdo summasiga (:total) teng bo\'lishi kerak.',
        'customer_required' => 'Qarzga savdo uchun mijozni tanlang.',
        'already_returned' => 'Bu savdo to\'liq qaytarilgan.',
        'nothing_to_return' => 'Qaytarish uchun mahsulot yo\'q.',
        'return_exceeds' => '":product" bo\'yicha qaytarish miqdori sotilgandan ko\'p. Qaytarish mumkin: :available.',
        'refund_debt_invalid' => 'Qarzdan ayirish mumkin emas. Qolgan qarz: :remaining.',
        'debt_note' => 'Savdo #:id',
        'movement_note' => 'Savdo #:id',
        'return_movement_note' => 'Savdo #:id qaytarildi',
        'return_debt_note' => 'Savdo #:id qaytarildi',
        'methods' => [
            'cash' => 'Naqd',
            'card' => 'Karta',
            'debt' => 'Qarz',
            'mixed' => 'Aralash',
        ],
        'receipt' => [
            'subtotal' => 'Summa',
            'discount' => 'Chegirma',
            'total' => 'Jami',
            'payment' => 'To\'lov',
            'debt' => 'Qarzga',
            'customer' => 'Mijoz',
            'returned' => 'Qaytarildi',
        ],
    ],

    'expense' => [
        'created' => 'Xarajat yozildi.',
        'deleted' => 'Xarajat o\'chirildi.',
    ],

    'billing' => [
        'already_pro' => 'Pro tarif allaqachon faol.',
        'checkout_created' => 'To\'lov yaratildi. To\'lov sahifasiga o\'ting.',
    ],

    'backup' => [
        'created' => 'Zaxira nusxa yaratildi.',
        'deleted' => 'Zaxira nusxa o\'chirildi.',
        'file_missing' => 'Zaxira fayli topilmadi.',
    ],
];
