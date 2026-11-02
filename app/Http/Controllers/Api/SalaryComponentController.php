<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\SalaryComponentService;
use App\Application\Services\TenantCompanyScope;
use App\Domain\Payroll\Models\SalaryComponent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalaryComponent\StoreSalaryComponentRequest;
use App\Http\Requests\SalaryComponent\UpdateSalaryComponentRequest;
use App\Http\Resources\SalaryComponentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SalaryComponentController extends Controller
{
    public function __construct(
        private readonly SalaryComponentService $service,
        private readonly TenantCompanyScope $tenantScope,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 100);
        $companyId = $this->tenantScope->companyIdFor(
            $request->user(),
            $request->integer('company_id') ?: null,
        );

        // Cotisations légales (is_statutory) masquées du catalogue « Composants de paie ».
        // ?include_statutory=1 pour le builder de structure (mode manuel).
        $includeStatutory = $request->boolean('include_statutory');

        return SalaryComponentResource::collection(
            $this->service->paginate(
                $perPage,
                $companyId ? (int) $companyId : null,
                $includeStatutory,
            )
        );
    }

    public function store(StoreSalaryComponentRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->user()->role !== 'super_admin') {
            $data['company_id'] = $this->tenantScope->companyIdFor($request->user());
        }

        $component = $this->service->create($data);

        return (new SalaryComponentResource($component))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, SalaryComponent $salary_component): SalaryComponentResource
    {
        $this->tenantScope->ensureOwnsCompany($request->user(), $salary_component->company_id);

        return new SalaryComponentResource($salary_component);
    }

    public function update(UpdateSalaryComponentRequest $request, SalaryComponent $salary_component): SalaryComponentResource
    {
        $this->tenantScope->ensureOwnsCompany($request->user(), $salary_component->company_id);
        $updated = $this->service->update($salary_component, $request->validated());

        return new SalaryComponentResource($updated);
    }

    public function destroy(Request $request, SalaryComponent $salary_component): JsonResponse
    {
        $this->tenantScope->ensureOwnsCompany($request->user(), $salary_component->company_id);
        if ($salary_component->is_statutory) {
            return response()->json([
                'message' => 'Les cotisations légales ne peuvent pas être supprimées. Passez en mode automatique dans les paramètres de paie pour les masquer.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->service->delete($salary_component);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
