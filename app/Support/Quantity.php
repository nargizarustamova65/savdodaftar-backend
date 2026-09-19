<?php

namespace App\Support;

final class Quantity
{
    /**
     * 12 -> "12 dona", 1.5 -> "1.5 kg", signed: -4 -> "-4 dona"
     */
    public static function format(float|int|string $qty, ?string $unit = null, bool $signed = false): string
    {
        $qty = (float) $qty;
        $abs = abs($qty);

        $decimals = match (true) {
            floor($abs) == $abs => 0,
            round($abs, 1) == $abs => 1,
            default => 3,
        };

        $formatted = number_format($abs, $decimals, '.', ' ');

        if ($signed) {
            $formatted = ($qty < 0 ? '-' : '+').$formatted;
        }

        return $unit ? $formatted.' '.self::unitLabel($unit) : $formatted;
    }

    public static function unitLabel(string $unit): string
    {
        return trans()->has('units.'.$unit) ? __('units.'.$unit) : $unit;
    }
}
