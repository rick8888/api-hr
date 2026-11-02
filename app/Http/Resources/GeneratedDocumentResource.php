<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\DocumentTemplate\Models\GeneratedDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GeneratedDocument */
class GeneratedDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'template_code' => $this->template_code,
            'template_version' => $this->template_version,
            'name' => $this->name,
            'file_path' => $this->file_path,
            'file_url' => $this->file_path ? asset('storage/'.$this->file_path) : null,
            'employee_document_id' => $this->employee_document_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
