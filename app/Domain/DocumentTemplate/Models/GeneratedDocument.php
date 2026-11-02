<?php

declare(strict_types=1);

namespace App\Domain\DocumentTemplate\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\EmployeeDocument\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'document_template_id',
        'template_code',
        'template_version',
        'name',
        'file_path',
        'payload_snapshot',
        'employee_document_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'payload_snapshot' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    public function employeeDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
