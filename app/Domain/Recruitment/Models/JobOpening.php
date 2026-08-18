<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Organization\Models\Designation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOpening extends Model
{
    use SoftDeletes;

    public const TYPES = ['full_time', 'part_time', 'contract'];

    public const STATUSES = ['open', 'closed'];

    protected $fillable = [
        'company_id',
        'designation_id',
        'title',
        'department',
        'location',
        'type',
        'status',
        'description',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
