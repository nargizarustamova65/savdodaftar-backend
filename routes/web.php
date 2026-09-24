<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminController::class, 'login'])->name('login');
    Route::post('/login', [AdminController::class, 'authenticate'])->name('authenticate');
    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/users/{user}', [AdminController::class, 'user'])->name('user');
        Route::delete('/users/{user}', [AdminController::class, 'block'])->name('block');
        Route::post('/users/{user}/unblock', [AdminController::class, 'unblock'])->name('unblock');
        Route::get('/notifications', [AdminController::class, 'notifications'])->name('notifications');
        Route::post('/notifications', [AdminController::class, 'sendNotification'])->name('notifications.send');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
    });
});
