<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function showLogin(): View
    {
        return view('admin.auth.login', ['meta' => ['title' => 'ورود']]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'تلاش‌های ناموفق بیش از حد. لطفا بعدا تلاش کنید.',
            ]);
        }

        if (! Auth::guard('landlord')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'email' => 'ایمیل یا رمز عبور نادرست است.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('landlord')->user();

        $account = $user->preferredAccount();

        if ($account !== null) {
            $request->session()->put('store.account', $account->id);
        } else {
            $request->session()->forget('store.account');
        }

        $this->audit->log('auth.login', $user, [], $account?->id);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard('landlord')->user();

        if ($user !== null) {
            $this->audit->log('auth.logout', $user);
        }

        Auth::guard('landlord')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
