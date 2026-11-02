<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Company\CompanyData;
use App\Application\Services\CompanyService;
use App\Domain\Company\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\ToggleCompanyStatusRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->role === 'super_admin', Response::HTTP_FORBIDDEN);

        return CompanyResource::collection(
            $this->service->paginate(
                (int) $request->query('per_page', 15),
                $request->integer('country_id') ?: null,
            )
        );
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = $this->service->create(CompanyData::fromArray($request->validated()));

        return (new CompanyResource($company))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Company $company): CompanyResource
    {
        abort_unless($request->user()?->role === 'super_admin', Response::HTTP_FORBIDDEN);

        $company->load('country');

        return new CompanyResource($company);
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        $current = array_merge(
            [
                'country_id' => $company->country_id,
                'name' => $company->name,
                'code' => $company->code,
                'legal_name' => $company->legal_name,
                'tax_id' => $company->tax_id,
                'registration_number' => $company->registration_number,
                'email' => $company->email,
                'phone' => $company->phone,
                'currency' => $company->currency,
                'is_active' => $company->is_active,
                'settings' => $company->settings ?? [],
            ],
            $request->validated()
        );

        $updated = $this->service->update($company, CompanyData::fromArray($current));

        return new CompanyResource($updated);
    }

    public function toggleStatus(ToggleCompanyStatusRequest $request, Company $company): CompanyResource
    {
        $updated = $this->service->toggleStatus($company, (bool) $request->validated('is_active'));

        return new CompanyResource($updated);
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        abort_unless($request->user()?->role === 'super_admin', Response::HTTP_FORBIDDEN);

        $this->service->delete($company);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
