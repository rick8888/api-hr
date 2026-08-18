<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Loan\LoanData;
use App\Application\Services\LoanService;
use App\Domain\Loan\Models\Loan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loan\StoreLoanRequest;
use App\Http\Requests\Loan\UpdateLoanStatusRequest;
use App\Http\Resources\LoanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'employee_id' => $request->query('employee_id'),
            'employee_code' => $request->query('employee_code'),
            'mine' => $request->query('mine'),
        ];

        return LoanResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        unset($validated['documents']);

        $row = $this->service->createForActor(
            $request->user(),
            LoanData::fromArray($validated),
            $request->file('documents', []) ?? [],
        );

        return (new LoanResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Loan $loan): LoanResource
    {
        return new LoanResource(
            $this->service->findForActor($request->user(), $loan)
        );
    }

    public function updateStatus(
        UpdateLoanStatusRequest $request,
        Loan $loan,
    ): LoanResource {
        $validated = $request->validated();

        $updated = $this->service->updateStatusForActor(
            $request->user(),
            $loan,
            (string) $validated['status'],
            $validated['reject_reason'] ?? null,
        );

        return new LoanResource($updated);
    }

    public function destroy(Request $request, Loan $loan): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $loan);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
