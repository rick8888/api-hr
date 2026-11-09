<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\EmployeeOffboardingService;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOffboardingTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\UpdateOffboardingTaskRequest;
use App\Http\Resources\EmployeeOffboardingTaskResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeOffboardingController extends Controller
{
    public function __construct(private readonly EmployeeOffboardingService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return EmployeeOffboardingTaskResource::collection(
            $this->service->listForActor($request->user())
        );
    }

    public function start(Request $request, Employee $employee): AnonymousResourceCollection
    {
        return EmployeeOffboardingTaskResource::collection(
            $this->service->startForActor($request->user(), $employee)
        );
    }

    public function updateTask(
        UpdateOffboardingTaskRequest $request,
        Employee $employee,
        EmployeeOffboardingTask $task,
    ): AnonymousResourceCollection {
        $this->service->updateTaskForActor(
            $request->user(),
            $employee,
            $task,
            (string) $request->validated('status'),
        );

        return EmployeeOffboardingTaskResource::collection(
            $this->service->listForActor($request->user())
                ->where('employee_id', $employee->id)
                ->values()
        );
    }
}
