<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\EquipmentAssignmentService;
use App\Domain\Equipment\Models\EquipmentAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\ReportEquipmentIssueRequest;
use App\Http\Resources\EquipmentAssignmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EquipmentAssignmentController extends Controller
{
    public function __construct(private readonly EquipmentAssignmentService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return EquipmentAssignmentResource::collection(
            $this->service->listForActor($request->user(), [
                'mine' => $request->query('mine'),
                'status' => $request->query('status'),
                'equipment_id' => $request->query('equipment_id'),
                'employee_id' => $request->query('employee_id'),
            ])
        );
    }

    public function show(Request $request, EquipmentAssignment $equipment_assignment): EquipmentAssignmentResource
    {
        return new EquipmentAssignmentResource(
            $this->service->findForActor($request->user(), $equipment_assignment)
        );
    }

    public function reportIssue(
        ReportEquipmentIssueRequest $request,
        EquipmentAssignment $equipment_assignment,
    ): EquipmentAssignmentResource {
        $updated = $this->service->reportIssueForActor(
            $request->user(),
            $equipment_assignment,
            (string) $request->validated('note'),
        );

        return new EquipmentAssignmentResource($updated);
    }

    public function returnItem(Request $request, EquipmentAssignment $equipment_assignment): EquipmentAssignmentResource
    {
        $updated = $this->service->returnForActor(
            $request->user(),
            $equipment_assignment,
        );

        return new EquipmentAssignmentResource($updated);
    }
}
