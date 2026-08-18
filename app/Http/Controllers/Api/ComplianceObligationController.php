<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Compliance\ComplianceObligationData;
use App\Application\Services\ComplianceObligationService;
use App\Domain\Compliance\Models\ComplianceObligation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Compliance\StoreComplianceObligationRequest;
use App\Http\Requests\Compliance\UpdateComplianceObligationRequest;
use App\Http\Resources\ComplianceObligationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ComplianceObligationController extends Controller
{
    public function __construct(private readonly ComplianceObligationService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $isActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : null;

        return ComplianceObligationResource::collection(
            $this->service->paginate(
                (int) $request->query('per_page', 15),
                $request->integer('country_id') ?: null,
                $request->query('type') ?: null,
                $isActive,
            )
        );
    }

    public function store(StoreComplianceObligationRequest $request): JsonResponse
    {
        $obligation = $this->service->create(
            ComplianceObligationData::fromArray($request->validated())
        );

        return (new ComplianceObligationResource($obligation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ComplianceObligation $compliance_obligation): ComplianceObligationResource
    {
        $obligation = $this->service->find($compliance_obligation->id) ?? $compliance_obligation;

        return new ComplianceObligationResource($obligation);
    }

    public function update(
        UpdateComplianceObligationRequest $request,
        ComplianceObligation $compliance_obligation,
    ): ComplianceObligationResource {
        $updated = $this->service->updateFromValidated(
            $compliance_obligation,
            $request->validated()
        );

        return new ComplianceObligationResource($updated);
    }

    public function destroy(ComplianceObligation $compliance_obligation): JsonResponse
    {
        $this->service->delete($compliance_obligation);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
