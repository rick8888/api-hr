<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TenantCompanyScope
{
    public function companyIdFor(User $actor, ?int $requestedCompanyId = null): ?int
    {
        if ($actor->role === 'super_admin') {
            return $requestedCompanyId;
        }

        if ($actor->company_id === null) {
            throw new AuthorizationException('Un compte entreprise actif est requis.');
        }

        return (int) $actor->company_id;
    }

    public function ensureOwnsCompany(User $actor, int|string|null $companyId): void
    {
        if ($actor->role === 'super_admin') {
            return;
        }

        if (
            $actor->company_id === null
            || $companyId === null
            || (int) $actor->company_id !== (int) $companyId
        ) {
            throw new NotFoundHttpException('Ressource introuvable.');
        }
    }
}
