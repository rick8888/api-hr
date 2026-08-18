<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Organization\HolidayData;
use App\Application\Services\HolidayService;
use App\Domain\Organization\Models\Holiday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreHolidayRequest;
use App\Http\Requests\Organization\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class HolidayController extends Controller
{
    public function __construct(private readonly HolidayService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'year' => $request->query('year'),
            'type' => $request->query('type'),
            'active' => $request->query('active'),
            'visible_to_employees' => $request->query('visible_to_employees'),
        ];

        return HolidayResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $holiday = $this->service->createForActor(
            $request->user(),
            HolidayData::fromArray($request->validated())
        );

        return (new HolidayResource($holiday))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Holiday $holiday): HolidayResource
    {
        return new HolidayResource(
            $this->service->findForActor($request->user(), $holiday)
        );
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): HolidayResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $holiday,
            $request->validated()
        );

        return new HolidayResource($updated);
    }

    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $holiday);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleStatus(Request $request, Holiday $holiday): HolidayResource
    {
        $updated = $this->service->toggleStatusForActor($request->user(), $holiday);

        return new HolidayResource($updated);
    }
}
