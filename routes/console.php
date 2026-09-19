<?php

use Illuminate\Support\Facades\Schedule;

// Eski OTP kodlarini har kuni tozalash (App\Models\OtpCode::prunable)
Schedule::command('model:prune')->daily();
