<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Training\TrainingData;
use App\Application\Services\TrainingService;
use App\Domain\Training\Models\Training;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreTrainingRequest;
use App\Http\Requests\Training\UpdateTrainingRequest;
use App\Http\Resources\TrainingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TrainingController extends Controller
{
    public function __construct(private readonly TrainingService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'category' => $request->query('category'),
            'status' => $request->query('status'),
            'active' => $request->query('active'),
        ];

        if (! empty($filters['category'])) {
            $filters['category'] = TrainingData::normalizeCategory((string) $filters['category']);
        }

        return TrainingResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreTrainingRequest $request): JsonResponse
    {
        $row = $this->service->createForActor(
            $request->user(),
            TrainingData::fromArray($request->validated()),
        );

        return (new TrainingResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Training $training): TrainingResource
    {
        return new TrainingResource(
            $this->service->findForActor($request->user(), $training)
        );
    }

    public function update(UpdateTrainingRequest $request, Training $training): TrainingResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $training,
            $request->validated(),
        );

        return new TrainingResource($updated);
    }

    public function destroy(Request $request, Training $training): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $training);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
