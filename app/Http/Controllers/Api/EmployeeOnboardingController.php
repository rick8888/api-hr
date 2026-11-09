<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\EmployeeOnboardingService;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOnboardingTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\UpdateOnboardingTaskRequest;
use App\Http\Resources\EmployeeOnboardingTaskResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeOnboardingController extends Controller
{
    public function __construct(private readonly EmployeeOnboardingService $service) {}

    public function index(Request $request, Employee $employee): AnonymousResourceCollection
    {
        return EmployeeOnboardingTaskResource::collection(
            $this->service->tasksForActor($request->user(), $employee)
        );
    }

    public function updateTask(
        UpdateOnboardingTaskRequest $request,
        Employee $employee,
        EmployeeOnboardingTask $task,
    ): AnonymousResourceCollection {
        $this->service->updateTaskForActor(
            $request->user(),
            $employee,
            $task,
            (string) $request->validated('status'),
        );

        return EmployeeOnboardingTaskResource::collection(
            $this->service->tasksForActor($request->user(), $employee)
        );
    }
}
