<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\EmployeeProbationService;
use App\Domain\Employee\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeProbationReviewRequest;
use App\Http\Resources\EmployeeProbationReviewResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeProbationController extends Controller
{
    public function __construct(private readonly EmployeeProbationService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return EmployeeProbationReviewResource::collection(
            $this->service->reviewsForActor(request()->user())
        );
    }

    public function store(
        StoreEmployeeProbationReviewRequest $request,
        Employee $employee,
    ): EmployeeProbationReviewResource {
        $review = $this->service->recordForActor(
            $request->user(),
            $employee,
            $request->validated(),
        );

        return new EmployeeProbationReviewResource($review);
    }
}
