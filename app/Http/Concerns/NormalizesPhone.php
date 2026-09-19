<?php

namespace App\Http\Concerns;

trait NormalizesPhone
{
    /**
     * "998 90 123-45-67", "901234567" kabi kiritishlarni +998XXXXXXXXX ko'rinishiga keltiradi.
     */
    protected function normalizePhone(mixed $phone): mixed
    {
        if (! is_string($phone)) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return $phone;
        }

        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }

        return '+'.$digits;
    }
}
