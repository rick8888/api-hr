<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Services\TenantCompanyScope;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class TenantCompanyScopeTest extends TestCase
{
    public function test_tenant_scope_ignores_requested_company_id(): void
    {
        $actor = new User;
        $actor->role = 'tenant';
        $actor->company_id = 17;

        $this->assertSame(17, app(TenantCompanyScope::class)->companyIdFor($actor, 29));
    }

    public function test_super_admin_can_select_company_or_all_companies(): void
    {
        $actor = new User;
        $actor->role = 'super_admin';

        $scope = app(TenantCompanyScope::class);

        $this->assertSame(29, $scope->companyIdFor($actor, 29));
        $this->assertNull($scope->companyIdFor($actor));
    }

    public function test_tenant_cannot_access_another_company_resource(): void
    {
        $actor = new User;
        $actor->role = 'tenant';
        $actor->company_id = 17;

        $this->expectException(NotFoundHttpException::class);

        app(TenantCompanyScope::class)->ensureOwnsCompany($actor, 29);
    }
}
