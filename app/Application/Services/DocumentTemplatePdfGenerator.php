<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use App\Domain\DocumentTemplate\Models\GeneratedDocument;
use App\Domain\Employee\Models\Employee;
use App\Domain\EmployeeDocument\Repositories\EmployeeDocumentRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class DocumentTemplatePdfGenerator
{
    public function __construct(
        private readonly DocumentTemplateMergeContext $contextBuilder,
        private readonly DocumentTemplateRenderer $renderer,
        private readonly DocumentTemplatePdfService $pdfService,
        private readonly EmployeeDocumentRepositoryInterface $employeeDocuments,
        private readonly AuditLogger $audit,
    ) {
    }

    public function generateAndArchive(
        User $actor,
        Employee $employee,
        DocumentTemplate $template,
    ): GeneratedDocument {
        if ($template->channel !== 'pdf') {
            throw new AuthorizationException('Le modèle doit être de type PDF.');
        }

        if ($template->status !== 'published') {
            throw new AuthorizationException('Le modèle doit être publié.');
        }

        if ($template->company_id !== null && (int) $template->company_id !== (int) $employee->company_id) {
            throw new AuthorizationException('Modèle introuvable.');
        }

        $employee->loadMissing('company');
        $context = $this->contextBuilder->build($employee);
        $body = $this->renderer->render($template->body_html, $context['flat']);
        $relativePath = 'generated-documents/'.$employee->company_id.'/'.$employee->id.'/'.uniqid('', true).'.pdf';

        return DB::transaction(function () use ($actor, $employee, $template, $context, $body, $relativePath) {
            $this->pdfService->store($template->name, $body, $relativePath);

            $category = $template->document_category_code ?: 'other';
            $docName = $template->name.' — '.$employee->full_name;

            $employeeDoc = $this->employeeDocuments->create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'name' => $docName,
                'reference' => strtoupper($template->code).'-'.now()->format('Ymd'),
                'category' => $category,
                'issue_date' => now()->toDateString(),
                'expiry_date' => null,
                'status' => 'verified',
                'file_path' => $relativePath,
                'manager_name' => $actor->name ?? $actor->email,
                'reject_reason' => null,
                'reviewed_at' => now()->toDateTimeString(),
            ]);

            $generated = GeneratedDocument::query()->create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'document_template_id' => $template->id,
                'template_code' => $template->code,
                'template_version' => $template->version,
                'name' => $docName,
                'file_path' => $relativePath,
                'payload_snapshot' => $context['tree'],
                'employee_document_id' => $employeeDoc->id,
                'created_by' => $actor->id,
            ]);

            $this->audit->log('document_template.generated', $generated, [
                'employee_id' => $employee->id,
                'template_code' => $template->code,
            ]);

            return $generated->load('employeeDocument');
        });
    }
}
