<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\CompanyUserService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyUser\StoreCompanyUserRequest;
use App\Http\Requests\CompanyUser\UpdateCompanyUserRequest;
use App\Http\Resources\CompanyUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CompanyUserController extends Controller
{
    public function __construct(private readonly CompanyUserService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = $request->query('roles');
        if (is_string($roles)) {
            $roles = array_filter(array_map('trim', explode(',', $roles)));
        }
        if (! is_array($roles)) {
            $roles = null;
        }

        return CompanyUserResource::collection(
            $this->service->listForActor($request->user(), $roles)
        );
    }

    public function store(StoreCompanyUserRequest $request): JsonResponse
    {
        $user = $this->service->createAdmin($request->user(), $request->validated());

        return (new CompanyUserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCompanyUserRequest $request, User $user): CompanyUserResource
    {
        $updated = $this->service->updateAdmin(
            $request->user(),
            $user,
            $request->validated(),
        );

        return new CompanyUserResource($updated);
    }
}
