<?php

namespace App\Services\Billing;

use App\Models\AdminSetting;

trait UsesAdminPricing
{
    protected function configuredPrice(string $key, mixed $fallback): float
    {
        try { return (float) AdminSetting::value($key, $fallback); } catch (\Throwable) { return (float) $fallback; }
    }
}
