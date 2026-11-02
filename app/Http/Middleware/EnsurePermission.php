<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePermission
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$permissions  Clés séparées par `|` = OU logique.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $required = [];
        foreach ($permissions as $permission) {
            foreach (explode('|', $permission) as $key) {
                $key = trim($key);
                if ($key !== '') {
                    $required[] = $key;
                }
            }
        }

        if ($required === []) {
            return $next($request);
        }

        if (! $user->hasAnyPermission(...$required)) {
            abort(Response::HTTP_FORBIDDEN, 'Permission refusée.');
        }

        return $next($request);
    }
}
