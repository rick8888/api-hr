<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Company\Models\Company;
use App\Domain\Platform\Models\Role;
use App\Domain\Platform\RolePermissionCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** @var list<string>|null */
    private ?array $resolvedPermissions = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->role !== 'tenant' || $user->company_id === null) {
                return;
            }

            $otherTenantExists = static::query()
                ->where('company_id', $user->company_id)
                ->where('role', 'tenant')
                ->when($user->exists, fn ($q) => $q->whereKeyNot($user->id))
                ->exists();

            if ($otherTenantExists) {
                throw ValidationException::withMessages([
                    'role' => ['Une entreprise ne peut avoir qu\'un seul compte tenant.'],
                ]);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        if ($this->resolvedPermissions !== null) {
            return $this->resolvedPermissions;
        }

        if ($this->role === 'super_admin') {
            return $this->resolvedPermissions = RolePermissionCatalog::keys();
        }

        $role = Role::query()
            ->where('slug', $this->role)
            ->where('is_active', true)
            ->first();

        $permissions = is_array($role?->permissions) ? array_values($role->permissions) : [];

        return $this->resolvedPermissions = array_values(array_unique(
            array_filter($permissions, static fn ($key) => is_string($key) && $key !== '')
        ));
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }

        return in_array($permission, $this->permissionKeys(), true);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
