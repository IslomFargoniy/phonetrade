<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Telefon raqami har xil yozilishi mumkin (+998 90 ..., 90 ...), shuning uchun
     * qidirishdan oldin bitta ko'rinishga keltiriladi.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $phone = Phone::normalize($request->string(Fortify::username())->toString());

            if ($phone === null) {
                return null;
            }

            $user = User::query()->where('phone', $phone)->first();

            if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
                return null;
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $phone = Phone::normalize($request->string(Fortify::username())->toString())
                ?? $request->string(Fortify::username())->toString();

            return Limit::perMinute(5)->by($phone.'|'.$request->ip());
        });
    }
}
