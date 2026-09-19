<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Sms\ArraySmsSender;
use App\Services\Sms\EskizSmsService;
use App\Services\Sms\LogSmsService;
use App\Services\Sms\SmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsSender::class, function () {
            return match (config('services.sms.driver')) {
                'eskiz' => new EskizSmsService(config('services.sms.eskiz')),
                'array' => new ArraySmsSender,
                default => new LogSmsService,
            };
        });
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'customer' => Customer::class,
            'debt' => Debt::class,
            'debt_payment' => DebtPayment::class,
            'product' => Product::class,
            'stock_movement' => StockMovement::class,
            'sale' => Sale::class,
            'sale_item' => SaleItem::class,
            'sale_return' => SaleReturn::class,
        ]);

        // OTP endpointlari uchun IP bo'yicha umumiy himoya (raqam bo'yicha limit OtpService'da)
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // PIN tekshirish (foydalanuvchi bo'yicha aniq limit PinService'da)
        RateLimiter::for('pin', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
    }
}
