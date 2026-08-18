<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Tax\LegalTaxData;
use App\Application\Services\LegalTaxService;
use App\Domain\Tax\Models\LegalTax;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreLegalTaxRequest;
use App\Http\Requests\Tax\UpdateLegalTaxRequest;
use App\Http\Resources\LegalTaxResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LegalTaxController extends Controller
{
    public function __construct(private readonly LegalTaxService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return LegalTaxResource::collection(
            $this->service->paginate(
                (int) $request->query('per_page', 15),
                $request->integer('country_id') ?: null,
            )
        );
    }

    public function store(StoreLegalTaxRequest $request): JsonResponse
    {
        $tax = $this->service->create(LegalTaxData::fromArray($request->validated()));

        return (new LegalTaxResource($tax))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(LegalTax $legal_tax): LegalTaxResource
    {
        $tax = $this->service->find($legal_tax->id) ?? $legal_tax;

        return new LegalTaxResource($tax);
    }

    public function update(UpdateLegalTaxRequest $request, LegalTax $legal_tax): LegalTaxResource
    {
        $updated = $this->service->updateFromValidated($legal_tax, $request->validated());

        return new LegalTaxResource($updated);
    }

    public function destroy(LegalTax $legal_tax): JsonResponse
    {
        $this->service->delete($legal_tax);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
