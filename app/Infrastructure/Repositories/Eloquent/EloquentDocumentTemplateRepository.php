<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use App\Domain\DocumentTemplate\Repositories\DocumentTemplateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentDocumentTemplateRepository implements DocumentTemplateRepositoryInterface
{
    public function listForAdmin(?int $companyId, bool $includePlatform): Collection
    {
        return DocumentTemplate::query()
            ->when(
                $companyId !== null && $includePlatform,
                fn ($q) => $q->where(function ($inner) use ($companyId) {
                    $inner->whereNull('company_id')->orWhere('company_id', $companyId);
                }),
            )
            ->when(
                $companyId !== null && ! $includePlatform,
                fn ($q) => $q->where('company_id', $companyId),
            )
            ->when(
                $companyId === null,
                fn ($q) => $q->whereNull('company_id'),
            )
            ->orderBy('sort_order')
            ->orderBy('family')
            ->orderBy('name')
            ->get();
    }

    public function listPublishedForCompany(int $companyId, ?string $channel = null): Collection
    {
        return DocumentTemplate::query()
            ->where('status', 'published')
            ->where('is_active', true)
            ->when($channel !== null, fn ($q) => $q->where('channel', $channel))
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?DocumentTemplate
    {
        return DocumentTemplate::query()->find($id);
    }

    public function findPublishedByCode(int $companyId, string $code, string $locale = 'fr'): ?DocumentTemplate
    {
        $company = DocumentTemplate::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('is_active', true)
            ->first();

        if ($company !== null) {
            return $company;
        }

        return DocumentTemplate::query()
            ->whereNull('company_id')
            ->where('code', $code)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('is_active', true)
            ->first();
    }

    public function create(array $attributes): DocumentTemplate
    {
        return DocumentTemplate::query()->create($attributes);
    }

    public function update(DocumentTemplate $template, array $attributes): DocumentTemplate
    {
        $template->fill($attributes);
        $template->save();

        return $template->refresh();
    }

    public function delete(DocumentTemplate $template): bool
    {
        return (bool) $template->delete();
    }
}
