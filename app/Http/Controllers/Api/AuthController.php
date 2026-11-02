<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\AuditLogger;
use App\Application\Services\EmployeeProfileLinker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EmployeeProfileLinker $profiles,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'message' => 'Identifiants invalides.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->role !== 'super_admin') {
            $employee = $this->profiles->findForUser($user);
            if ($employee?->terminated_at !== null) {
                return response()->json([
                    'message' => 'Votre contrat est terminé. Contactez les ressources humaines.',
                ], Response::HTTP_FORBIDDEN);
            }
            if ($employee !== null && ! $employee->is_active) {
                return response()->json([
                    'message' => 'Votre compte est inactif. Contactez les ressources humaines.',
                ], Response::HTTP_FORBIDDEN);
            }
            if ($employee?->isRetired()) {
                return response()->json([
                    'message' => 'Votre compte est archivé (retraite). Contactez les ressources humaines.',
                ], Response::HTTP_FORBIDDEN);
            }
            if ($employee?->isSuspended()) {
                return response()->json([
                    'message' => 'Votre compte est suspendu. Contactez les ressources humaines.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $user->load('company');
        $token = $user->createToken('api')->plainTextToken;

        $this->audit->log('auth.login', $user, [
            'url' => '/auth/login',
        ], 'auth', $user->id);

        return response()->json([
            'token' => $token,
            'user' => new AuthUserResource($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();

        $this->audit->log('auth.logout', $user, [
            'url' => '/auth/logout',
        ], 'auth', $user?->id);

        $user?->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function me(Request $request): AuthUserResource
    {
        $request->user()->load('company');

        return new AuthUserResource($request->user());
    }
}
