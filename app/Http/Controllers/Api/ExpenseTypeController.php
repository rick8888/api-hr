<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\ExpenseTypeData;
use App\Application\Services\ExpenseTypeService;
use App\Domain\Platform\Models\ExpenseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreExpenseTypeRequest;
use App\Http\Requests\Platform\ToggleExpenseTypeStatusRequest;
use App\Http\Requests\Platform\UpdateExpenseTypeRequest;
use App\Http\Resources\ExpenseTypeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ExpenseTypeController extends Controller
{
    public function __construct(private readonly ExpenseTypeService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $types = $onlyActive ? $this->service->listActive() : $this->service->list();

        return ExpenseTypeResource::collection($types);
    }

    public function store(StoreExpenseTypeRequest $request): JsonResponse
    {
        $type = $this->service->create(ExpenseTypeData::fromArray($request->validated()));

        return (new ExpenseTypeResource($type))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ExpenseType $expense_type): ExpenseTypeResource
    {
        return new ExpenseTypeResource($expense_type);
    }

    public function update(
        UpdateExpenseTypeRequest $request,
        ExpenseType $expense_type,
    ): ExpenseTypeResource {
        $updated = $this->service->update($expense_type, $request->validated());

        return new ExpenseTypeResource($updated);
    }

    public function toggleStatus(
        ToggleExpenseTypeStatusRequest $request,
        ExpenseType $expense_type,
    ): ExpenseTypeResource {
        $updated = $this->service->toggleStatus(
            $expense_type,
            (bool) $request->validated('is_active'),
        );

        return new ExpenseTypeResource($updated);
    }

    public function destroy(Request $request, ExpenseType $expense_type): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['super_admin', 'tenant', 'admin'], true),
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($expense_type);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
