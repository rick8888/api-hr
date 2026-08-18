<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Leave\CompensatoryOffData;
use App\Application\Services\CompensatoryOffService;
use App\Domain\Leave\Models\CompensatoryOff;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreCompensatoryOffRequest;
use App\Http\Requests\Leave\UpdateCompensatoryOffStatusRequest;
use App\Http\Resources\CompensatoryOffResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CompensatoryOffController extends Controller
{
    public function __construct(private readonly CompensatoryOffService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'status' => $request->query('status'),
            'usage_status' => $request->query('usage_status'),
            'employee_id' => $request->query('employee_id'),
            'employee_code' => $request->query('employee_code'),
            'mine' => $request->query('mine'),
        ];

        return CompensatoryOffResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreCompensatoryOffRequest $request): JsonResponse
    {
        $row = $this->service->createForActor(
            $request->user(),
            CompensatoryOffData::fromArray($request->validated())
        );

        return (new CompensatoryOffResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, CompensatoryOff $compensatory_off): CompensatoryOffResource
    {
        return new CompensatoryOffResource(
            $this->service->findForActor($request->user(), $compensatory_off)
        );
    }

    public function updateStatus(
        UpdateCompensatoryOffStatusRequest $request,
        CompensatoryOff $compensatory_off,
    ): CompensatoryOffResource {
        $validated = $request->validated();

        $updated = $this->service->updateStatusForActor(
            $request->user(),
            $compensatory_off,
            (string) $validated['status'],
            $validated['reject_reason'] ?? null,
        );

        return new CompensatoryOffResource($updated);
    }

    public function destroy(Request $request, CompensatoryOff $compensatory_off): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $compensatory_off);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
