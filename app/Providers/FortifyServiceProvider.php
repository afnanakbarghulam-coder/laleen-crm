<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Middleware\EnsureActiveUser;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \Laravel\Fortify\Contracts\LoginResponse::class,
            fn () => new class implements \Laravel\Fortify\Contracts\LoginResponse {
                public function toResponse($request)
                {
                    $user = $request->user();
                    $target = $user?->defaultLandingUrl() ?? '/dashboard';

                    if ($request->wantsJson()) {
                        return response()->json([
                            'two_factor' => false,
                            'redirect' => $target,
                        ]);
                    }

                    if ($user && $user->isSalonStaff()) {
                        return redirect()->to($target);
                    }

                    return redirect()->intended($target);
                }
            }
        );
    }

    /**
     * Bootstrap any application services.
     */

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                if (!$user->is_active) {
                    throw ValidationException::withMessages([
                        Fortify::username() => [EnsureActiveUser::DEACTIVATED_MESSAGE],
                    ]);
                }

                return $user;
            }

            return null;
        });

        \Laravel\Fortify\Fortify::loginView(function () {
            return view('auth.login');
        });


        \Laravel\Fortify\Fortify::registerView(function () {
            return view('auth.register');
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());
            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
