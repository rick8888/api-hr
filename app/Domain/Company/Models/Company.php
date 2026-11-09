<?php

declare(strict_types=1);

namespace App\Domain\Company\Models;

use App\Domain\Employee\Models\Employee;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tax\Models\CompanyTax;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'country_id',
        'code',
        'name',
        'legal_name',
        'tax_id',
        'registration_number',
        'email',
        'phone',
        'address',
        'currency',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function companyTaxes(): HasMany
    {
        return $this->hasMany(CompanyTax::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
