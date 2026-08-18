<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\CountryService;
use App\Domain\Company\Models\Country;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCountryRequest;
use App\Http\Requests\Company\ToggleCountryStatusRequest;
use App\Http\Requests\Company\UpdateCountryRequest;
use App\Http\Resources\CountryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CountryController extends Controller
{
    public function __construct(private readonly CountryService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', true), FILTER_VALIDATE_BOOL);

        return CountryResource::collection($this->service->list($onlyActive));
    }

    public function store(StoreCountryRequest $request): JsonResponse
    {
        $country = $this->service->create($request->validated());

        return (new CountryResource($country))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Country $country): CountryResource
    {
        return new CountryResource($country);
    }

    public function update(UpdateCountryRequest $request, Country $country): CountryResource
    {
        $updated = $this->service->update($country, $request->validated());

        return new CountryResource($updated);
    }

    public function toggleStatus(
        ToggleCountryStatusRequest $request,
        Country $country,
    ): CountryResource {
        $updated = $this->service->toggleStatus(
            $country,
            (bool) $request->validated('is_active'),
        );

        return new CountryResource($updated);
    }

    public function destroy(Request $request, Country $country): JsonResponse
    {
        abort_unless(
            $request->user()?->role === 'super_admin',
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($country);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
