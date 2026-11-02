<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\DocumentTemplate\Models\GeneratedDocument;
use App\Domain\DocumentTemplate\Repositories\DocumentTemplateRepositoryInterface;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class EmployeeLifecycleDocumentService
{
    public function __construct(
        private readonly DocumentTemplateRepositoryInterface $templates,
        private readonly DocumentTemplatePdfGenerator $pdfGenerator,
    ) {
    }

    public function generatePdfForEvent(User $actor, Employee $employee, string $eventKey): ?GeneratedDocument
    {
        $map = config('document_templates.documents', []);
        $code = is_array($map) ? ($map[$eventKey] ?? null) : null;
        if (! is_string($code) || $code === '') {
            return null;
        }

        if ($employee->company_id === null) {
            return null;
        }

        $this->assertCanGenerate($actor, $employee);

        $template = $this->templates->findPublishedByCode((int) $employee->company_id, $code);
        if ($template === null) {
            Log::info('Modèle PDF cycle de vie introuvable.', [
                'employee_id' => $employee->id,
                'event' => $eventKey,
                'template_code' => $code,
            ]);

            return null;
        }

        return $this->pdfGenerator->generateAndArchive($actor, $employee->fresh(), $template);
    }

    private function assertCanGenerate(User $actor, Employee $employee): void
    {
        if (! $actor->hasPermission('employees.manage')) {
            throw new AuthorizationException('Permission insuffisante.');
        }

        Gate::forUser($actor)->authorize('view', $employee);
    }
}
