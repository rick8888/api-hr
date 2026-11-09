<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Employee\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class EmployeeAvatarUrl
{
    public static function for(mixed $employee, Request $request): ?string
    {
        if (! $employee instanceof Employee || ! $employee->avatar_path) {
            return null;
        }

        if (config('filesystems.disks.public.driver') === 'local') {
            return $request->getSchemeAndHttpHost().'/storage/'.ltrim($employee->avatar_path, '/');
        }

        return Storage::disk('public')->url($employee->avatar_path);
    }
}
