<?php

declare(strict_types=1);

namespace App\Application\DTO\DocumentTemplate;

use App\Domain\DocumentTemplate\Models\DocumentTemplate;

final class DocumentTemplateData
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly string $family,
        public readonly string $channel,
        public readonly string $locale,
        public readonly string $bodyHtml,
        public readonly ?string $subject = null,
        public readonly ?string $documentCategoryCode = null,
        public readonly int $sortOrder = 0,
        public readonly bool $isActive = true,
        public readonly ?int $companyId = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            code: (string) ($data['code'] ?? ''),
            family: (string) ($data['family'] ?? 'contracts'),
            channel: (string) ($data['channel'] ?? 'pdf'),
            locale: (string) ($data['locale'] ?? 'fr'),
            bodyHtml: (string) ($data['body_html'] ?? $data['bodyHtml'] ?? ''),
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            documentCategoryCode: isset($data['document_category_code'])
                ? (string) $data['document_category_code']
                : (isset($data['documentCategoryCode']) ? (string) $data['documentCategoryCode'] : null),
            sortOrder: (int) ($data['sort_order'] ?? $data['sortOrder'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? $data['isActive'] ?? true),
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return array_filter([
            'company_id' => $this->companyId,
            'name' => $this->name,
            'code' => $this->code,
            'family' => $this->family,
            'channel' => $this->channel,
            'locale' => $this->locale,
            'subject' => $this->subject,
            'body_html' => $this->bodyHtml,
            'document_category_code' => $this->documentCategoryCode,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ], static fn ($v) => $v !== null);
    }
}
