<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use App\Models\AdminSetting;
use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function login(): View { return view('admin.login'); }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $admin = AdminUser::where('email', $data['email'])->first();
        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            return back()->withErrors(['email' => 'Login yoki parol noto‘g‘ri.'])->withInput();
        }
        $request->session()->regenerate();
        $request->session()->put('admin_id', $admin->id);
        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_id');
        $request->session()->invalidate();
        return redirect()->route('admin.login');
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'users' => User::count(),
            'activeUsers' => User::whereNull('deleted_at')->count(),
            'payments' => Payment::where('status', Payment::STATUS_PAID)->count(),
            'revenue' => Payment::where('status', Payment::STATUS_PAID)->sum('amount'),
        ]);
    }

    public function users(Request $request): View
    {
        $users = User::with('subscriptions')->withTrashed()->latest()->paginate(25);
        return view('admin.users', compact('users'));
    }

    public function user(User $user): View
    {
        $user->load(['subscriptions', 'payments', 'customers', 'debts', 'products']);
        return view('admin.user', compact('user'));
    }

    public function block(User $user): RedirectResponse
    {
        $user->delete();
        return back()->with('status', 'Foydalanuvchi bloklandi.');
    }

    public function unblock(int $user): RedirectResponse
    {
        User::withTrashed()->findOrFail($user)->restore();
        return back()->with('status', 'Foydalanuvchi blokdan chiqarildi.');
    }

    public function notifications(): View
    {
        $notifications = AdminNotification::with('user')->latest()->paginate(25);
        return view('admin.notifications', compact('notifications'));
    }

    public function sendNotification(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'exists:users,id'], 'title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string']]);
        AdminNotification::create($data + ['sent_at' => now()]);
        return back()->with('status', 'Bildirishnoma saqlandi.');
    }

    public function settings(): View
    {
        return view('admin.settings', ['standard' => AdminSetting::value('standard_price', config('savdodaftar.billing.standard_price')), 'pro' => AdminSetting::value('pro_price', config('savdodaftar.billing.pro_price'))]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['standard_price' => ['required', 'integer', 'min:0'], 'pro_price' => ['required', 'integer', 'min:0']]);
        AdminSetting::put('standard_price', $data['standard_price']);
        AdminSetting::put('pro_price', $data['pro_price']);
        return back()->with('status', 'Tariflar yangilandi.');
    }
}
