<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentTemplate */
class DocumentTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'scope' => $this->company_id === null ? 'platform' : 'company',
            'name' => $this->name,
            'code' => $this->code,
            'family' => $this->family,
            'channel' => $this->channel,
            'locale' => $this->locale,
            'subject' => $this->subject,
            'body_html' => $this->body_html,
            'body_html_preview' => mb_strlen((string) $this->body_html) > 120
                ? mb_substr(strip_tags((string) $this->body_html), 0, 120).'…'
                : strip_tags((string) $this->body_html),
            'document_category_code' => $this->document_category_code,
            'version' => $this->version,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
