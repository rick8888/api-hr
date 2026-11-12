<?php

namespace App\Providers;

use App\Domain\Employee\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Employee::class => EmployeePolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        ResetPassword::createUrlUsing(static function ($user, string $token): string {
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
            $query = http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);

            return "{$frontendUrl}/reset-password?{$query}";
        });
    }
}
