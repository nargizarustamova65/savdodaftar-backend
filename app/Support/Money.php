<?php

namespace App\Support;

final class Money
{
    /**
     * 150000 -> "150 000 so'm"
     */
    public static function format(float|int|string $amount, bool $withCurrency = true): string
    {
        $amount = (float) $amount;
        $decimals = floor($amount) == $amount ? 0 : 2;
        $formatted = number_format($amount, $decimals, '.', ' ');

        return $withCurrency ? $formatted." so'm" : $formatted;
    }
}
