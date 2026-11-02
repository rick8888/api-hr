<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use App\Domain\DocumentTemplate\Models\GeneratedDocument;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\Response;

final class DocumentGenerationService
{
    public function __construct(
        private readonly DocumentTemplateService $templates,
        private readonly DocumentTemplateMergeContext $contextBuilder,
        private readonly DocumentTemplateRenderer $renderer,
        private readonly DocumentTemplatePdfService $pdfService,
        private readonly DocumentTemplatePdfGenerator $pdfGenerator,
        private readonly EmployeeService $employees,
    ) {
    }

    /** @param array<string, mixed> $extra */
    public function previewForActor(
        User $actor,
        Employee $employee,
        DocumentTemplate $template,
        array $extra = [],
    ): Response {
        $this->assertEmployeeAccess($actor, $employee);
        $this->assertTemplateUsable($employee, $template);

        $context = $this->contextBuilder->build($employee, null, $extra);
        $body = $this->renderer->render($template->body_html, $context['flat']);

        if ($template->channel === 'pdf') {
            $filename = $this->buildFilename($template, $employee);

            return $this->pdfService->stream($template->name, $body, $filename);
        }

        $subject = $template->subject
            ? $this->renderer->render($template->subject, $context['flat'])
            : $template->name;

        return response()->json([
            'channel' => $template->channel,
            'subject' => $subject,
            'body_html' => $body,
        ]);
    }

    /** @param array<string, mixed> $extra */
    public function generateForActor(
        User $actor,
        Employee $employee,
        DocumentTemplate $template,
        array $extra = [],
    ): GeneratedDocument {
        $this->assertEmployeeAccess($actor, $employee);
        $this->assertTemplateUsable($employee, $template);

        return $this->pdfGenerator->generateAndArchive($actor, $employee->fresh() ?? $employee, $template);
    }

    public function previewTemplateForActor(
        User $actor,
        DocumentTemplate $template,
        ?Employee $employee = null,
    ): Response {
        $this->templates->findForActor($actor, $template);

        if ($employee !== null) {
            $this->assertEmployeeAccess($actor, $employee);
            $this->assertTemplateUsable($employee, $template);
            $context = $this->contextBuilder->build($employee);
        } else {
            $context = $this->contextBuilder->buildSample();
        }

        $body = $this->renderer->render($template->body_html, $context['flat']);

        if ($template->channel === 'pdf') {
            $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $template->code.'-apercu') ?: 'apercu';

            return $this->pdfService->stream($template->name, $body, $filename);
        }

        $subjectRaw = $template->subject ?: $template->name;
        $subject = $this->renderer->render($subjectRaw, $context['flat']);

        return response()->json([
            'channel' => $template->channel,
            'subject' => $subject,
            'body_html' => $body,
        ]);
    }

    private function assertEmployeeAccess(User $actor, Employee $employee): void
    {
        if (! $actor->can('view', $employee)) {
            throw new AuthorizationException('Employé introuvable.');
        }

        if (! $actor->hasPermission('employees.manage')) {
            throw new AuthorizationException('Permission insuffisante pour générer un document.');
        }

        $this->employees->findForActor($actor, $employee);
    }

    private function assertTemplateUsable(Employee $employee, DocumentTemplate $template): void
    {
        if ($template->company_id !== null && (int) $template->company_id !== (int) $employee->company_id) {
            throw new AuthorizationException('Modèle introuvable.');
        }
    }

}
