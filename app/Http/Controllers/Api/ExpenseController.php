<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Expense\ExpenseData;
use App\Application\Services\ExpenseService;
use App\Domain\Expense\Models\Expense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseStatusRequest;
use App\Http\Resources\ExpenseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'date' => $request->query('date'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'employee_id' => $request->query('employee_id'),
            'employee_code' => $request->query('employee_code'),
            'mine' => $request->query('mine'),
        ];

        if (! empty($filters['type'])) {
            $filters['type'] = strtolower(str_replace([' ', '-'], '_', (string) $filters['type']));
        }

        return ExpenseResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        unset($validated['receipt']);

        $row = $this->service->createForActor(
            $request->user(),
            ExpenseData::fromArray($validated),
            $request->file('receipt'),
        );

        return (new ExpenseResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Expense $expense): ExpenseResource
    {
        return new ExpenseResource(
            $this->service->findForActor($request->user(), $expense)
        );
    }

    public function updateStatus(
        UpdateExpenseStatusRequest $request,
        Expense $expense,
    ): ExpenseResource {
        $validated = $request->validated();

        $updated = $this->service->updateStatusForActor(
            $request->user(),
            $expense,
            (string) $validated['status'],
            $validated['reject_reason'] ?? null,
        );

        return new ExpenseResource($updated);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $expense);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
