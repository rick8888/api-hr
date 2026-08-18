<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'working_days',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
