<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Organization\SiteData;
use App\Application\Services\SiteService;
use App\Domain\Organization\Models\Site;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreSiteRequest;
use App\Http\Requests\Organization\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SiteController extends Controller
{
    public function __construct(private readonly SiteService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return SiteResource::collection(
            $this->service->listForActor($request->user())
        );
    }

    public function store(StoreSiteRequest $request): JsonResponse
    {
        $site = $this->service->createForActor(
            $request->user(),
            SiteData::fromArray($request->validated())
        );

        return (new SiteResource($site))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Site $site): SiteResource
    {
        return new SiteResource(
            $this->service->findForActor($request->user(), $site)
        );
    }

    public function update(UpdateSiteRequest $request, Site $site): SiteResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $site,
            $request->validated()
        );

        return new SiteResource($updated);
    }

    public function destroy(Request $request, Site $site): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $site);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleStatus(Request $request, Site $site): SiteResource
    {
        $updated = $this->service->toggleStatusForActor($request->user(), $site);

        return new SiteResource($updated);
    }
}
