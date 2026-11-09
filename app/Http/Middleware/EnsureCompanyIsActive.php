<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Services\TenantSubscriptionService;
use App\Domain\Company\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanyIsActive
{
    public function __construct(private readonly TenantSubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (
            $user === null
            || in_array($user->role, ['super_admin', 'tenant'], true)
            || $user->company_id === null
        ) {
            return $next($request);
        }

        $company = Company::query()->find($user->company_id);
        $companyIsActive = $company !== null && (
            $company->is_active
            || $this->subscriptions->reactivateCompanyForInForceSubscription($company)
        );

        if (! $companyIsActive) {
            return response()->json([
                'message' => 'L’accès à votre entreprise est suspendu. Contactez le support.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
