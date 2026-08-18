<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Organization\TeamData;
use App\Application\Services\TeamService;
use App\Domain\Organization\Models\Team;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreTeamRequest;
use App\Http\Requests\Organization\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return TeamResource::collection(
            $this->service->listForActor($request->user())
        );
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = $this->service->createForActor(
            $request->user(),
            TeamData::fromArray($request->validated())
        );

        return (new TeamResource($team))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Team $team): TeamResource
    {
        return new TeamResource(
            $this->service->findForActor($request->user(), $team)
        );
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $team,
            $request->validated()
        );

        return new TeamResource($updated);
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $team);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleStatus(Request $request, Team $team): TeamResource
    {
        $updated = $this->service->toggleStatusForActor($request->user(), $team);

        return new TeamResource($updated);
    }
}
