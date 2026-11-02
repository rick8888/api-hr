<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\DocumentTemplate\DocumentTemplateData;
use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use App\Domain\DocumentTemplate\Repositories\DocumentTemplateRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DocumentTemplateService
{
    public function __construct(
        private readonly DocumentTemplateRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, DocumentTemplate> */
    public function listForActor(User $actor, bool $companyOnly = false): Collection
    {
        $this->assertCanManage($actor);

        if ($actor->role === 'super_admin') {
            return $this->repository->listForAdmin(null, false);
        }

        $companyId = $this->resolveCompanyId($actor);

        return $this->repository->listForAdmin($companyId, ! $companyOnly);
    }

    /** @return Collection<int, DocumentTemplate> */
    public function catalogForActor(User $actor, ?string $channel = 'pdf'): Collection
    {
        if (! $this->canUseCatalog($actor)) {
            throw new AuthorizationException('Accès non autorisé.');
        }

        $companyId = $this->resolveCompanyId($actor);
        $rows = $this->repository->listPublishedForCompany($companyId, $channel);

        return $this->dedupeByCode($rows);
    }

    public function createForActor(User $actor, DocumentTemplateData $data): DocumentTemplate
    {
        $this->assertCanManage($actor);

        $companyId = $actor->role === 'super_admin' ? null : $this->resolveCompanyId($actor);
        $payload = $data->toModelArray();
        $payload['company_id'] = $companyId;
        $payload['status'] = 'draft';
        $payload['version'] = 1;
        $payload['is_system'] = $actor->role === 'super_admin' && $companyId === null;

        $this->validateFamilyChannel($payload);

        return DB::transaction(function () use ($payload) {
            $template = $this->repository->create($payload);
            $this->audit->log('document_template.created', $template, ['code' => $template->code]);

            return $template;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, DocumentTemplate $template, array $validated): DocumentTemplate
    {
        $this->assertCanManage($actor);
        $this->assertCanTouch($actor, $template);

        if ($template->is_system && $actor->role !== 'super_admin') {
            throw ValidationException::withMessages([
                'template' => 'Ce modèle système ne peut être modifié que par la plateforme.',
            ]);
        }

        $merged = array_merge($this->modelToArray($template), $validated);
        if ($template->is_system) {
            $merged['code'] = $template->code;
        }

        $data = DocumentTemplateData::fromArray($merged);
        $payload = $data->toModelArray();
        unset($payload['company_id']);
        $this->validateFamilyChannel($payload);

        return DB::transaction(function () use ($template, $payload) {
            $updated = $this->repository->update($template, $payload);
            $this->audit->log('document_template.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function publishForActor(User $actor, DocumentTemplate $template): DocumentTemplate
    {
        $this->assertCanManage($actor);
        $this->assertCanTouch($actor, $template);

        return DB::transaction(function () use ($template) {
            $updated = $this->repository->update($template, [
                'status' => 'published',
                'published_at' => now(),
            ]);
            $this->audit->log('document_template.published', $updated, [
                'code' => $updated->code,
                'version' => $updated->version,
            ]);

            return $updated;
        });
    }

    public function duplicateForActor(User $actor, DocumentTemplate $source): DocumentTemplate
    {
        $this->assertCanManage($actor);

        if ($actor->role === 'super_admin') {
            throw ValidationException::withMessages([
                'template' => 'Utilisez la création directe pour les modèles plateforme.',
            ]);
        }

        $companyId = $this->resolveCompanyId($actor);

        if ($source->company_id !== null && (int) $source->company_id !== $companyId) {
            throw new AuthorizationException('Modèle introuvable.');
        }

        $existing = DocumentTemplate::query()
            ->where('company_id', $companyId)
            ->where('code', $source->code)
            ->where('locale', $source->locale)
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'code' => 'Un modèle personnalisé existe déjà pour ce code.',
            ]);
        }

        return DB::transaction(function () use ($source, $companyId) {
            $copy = $this->repository->create([
                'company_id' => $companyId,
                'name' => $source->name.' (personnalisé)',
                'code' => $source->code,
                'family' => $source->family,
                'channel' => $source->channel,
                'locale' => $source->locale,
                'subject' => $source->subject,
                'body_html' => $source->body_html,
                'document_category_code' => $source->document_category_code,
                'version' => 1,
                'status' => 'draft',
                'sort_order' => $source->sort_order,
                'is_system' => false,
                'is_active' => true,
            ]);

            $this->audit->log('document_template.duplicated', $copy, [
                'from_id' => $source->id,
                'code' => $copy->code,
            ]);

            return $copy;
        });
    }

    public function deleteForActor(User $actor, DocumentTemplate $template): bool
    {
        $this->assertCanManage($actor);
        $this->assertCanTouch($actor, $template);

        if ($template->is_system) {
            throw ValidationException::withMessages([
                'template' => 'Un modèle système ne peut pas être supprimé.',
            ]);
        }

        return DB::transaction(function () use ($template) {
            $this->audit->log('document_template.deleted', $template, ['code' => $template->code]);

            return $this->repository->delete($template);
        });
    }

    public function findForActor(User $actor, DocumentTemplate $template): DocumentTemplate
    {
        $this->assertCanManage($actor);
        $this->assertCanTouch($actor, $template);

        return $template;
    }

    public function resolvePublishedForEmployee(User $actor, string $code, string $locale = 'fr'): DocumentTemplate
    {
        if (! $this->canUseCatalog($actor)) {
            throw new AuthorizationException('Accès non autorisé.');
        }

        $companyId = $this->resolveCompanyId($actor);
        $template = $this->repository->findPublishedByCode($companyId, $code, $locale);

        if ($template === null) {
            throw ValidationException::withMessages([
                'template_code' => 'Modèle introuvable ou non publié.',
            ]);
        }

        return $template;
    }

    /** @param Collection<int, DocumentTemplate> $rows */
    private function dedupeByCode(Collection $rows): Collection
    {
        $byKey = [];
        foreach ($rows as $row) {
            $key = $row->code.'|'.$row->locale;
            $existing = $byKey[$key] ?? null;
            if ($existing === null) {
                $byKey[$key] = $row;

                continue;
            }
            if ($existing->company_id === null && $row->company_id !== null) {
                $byKey[$key] = $row;
            }
        }

        return new Collection(array_values($byKey));
    }

    /** @param array<string, mixed> $payload */
    private function validateFamilyChannel(array $payload): void
    {
        if (! in_array($payload['family'] ?? '', DocumentTemplate::FAMILIES, true)) {
            throw ValidationException::withMessages(['family' => 'Famille invalide.']);
        }
        if (! in_array($payload['channel'] ?? '', DocumentTemplate::CHANNELS, true)) {
            throw ValidationException::withMessages(['channel' => 'Canal invalide.']);
        }
    }

    private function assertCanManage(User $actor): void
    {
        if ($actor->role === 'super_admin') {
            return;
        }

        if (! $actor->hasPermission('settings.manage')) {
            throw new AuthorizationException('Permission insuffisante.');
        }
    }

    private function canUseCatalog(User $actor): bool
    {
        if ($actor->role === 'tenant') {
            return true;
        }

        return $actor->hasAnyPermission('employees.manage', 'employees.view', 'settings.manage');
    }

    private function resolveCompanyId(User $actor): int
    {
        if ($actor->company_id === null) {
            throw new AuthorizationException('Société requise.');
        }

        return (int) $actor->company_id;
    }

    private function assertCanTouch(User $actor, DocumentTemplate $template): void
    {
        if ($actor->role === 'super_admin') {
            if ($template->company_id !== null) {
                throw new AuthorizationException('Modèle introuvable.');
            }

            return;
        }

        if ($template->company_id === null) {
            return;
        }

        if ((int) $template->company_id !== (int) $actor->company_id) {
            throw new AuthorizationException('Modèle introuvable.');
        }
    }

    /** @return array<string, mixed> */
    private function modelToArray(DocumentTemplate $template): array
    {
        return [
            'name' => $template->name,
            'code' => $template->code,
            'family' => $template->family,
            'channel' => $template->channel,
            'locale' => $template->locale,
            'subject' => $template->subject,
            'body_html' => $template->body_html,
            'document_category_code' => $template->document_category_code,
            'sort_order' => $template->sort_order,
            'is_active' => $template->is_active,
        ];
    }
}
