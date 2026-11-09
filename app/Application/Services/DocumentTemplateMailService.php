<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\DocumentTemplate\Models\GeneratedDocument;
use App\Domain\DocumentTemplate\Repositories\DocumentTemplateRepositoryInterface;
use App\Domain\Employee\Models\Employee;
use App\Notifications\TemplatedMailNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class DocumentTemplateMailService
{
    public function __construct(
        private readonly DocumentTemplateRepositoryInterface $templates,
        private readonly DocumentTemplateMergeContext $contextBuilder,
        private readonly DocumentTemplateRenderer $renderer,
        private readonly AuditLogger $audit,
    ) {
    }

    public function sendForEvent(Employee $employee, string $eventKey, ?string $locale = null): bool
    {
        $map = config('document_templates.events', []);
        $code = is_array($map) ? ($map[$eventKey] ?? null) : null;
        if (! is_string($code) || $code === '') {
            Log::info('Aucun modèle e-mail associé à cet événement.', [
                'employee_id' => $employee->id,
                'event' => $eventKey,
            ]);

            return false;
        }

        return $this->sendByTemplateCode($employee, $code, $locale);
    }

    /** @param array<string, mixed> $extra */
    public function notificationForEvent(
        Employee $employee,
        string $eventKey,
        array $extra = [],
        ?string $locale = null,
    ): ?TemplatedMailNotification {
        if ($employee->company_id === null) {
            return null;
        }

        $map = config('document_templates.events', []);
        $code = is_array($map) ? ($map[$eventKey] ?? null) : null;
        if (! is_string($code) || $code === '') {
            Log::info('Aucun modèle e-mail associé à cet événement.', [
                'employee_id' => $employee->id,
                'event' => $eventKey,
            ]);

            return null;
        }

        $locale ??= (string) config('document_templates.default_locale', 'fr');
        $template = $this->templates->findPublishedByCode((int) $employee->company_id, $code, $locale);
        if ($template === null || $template->channel !== 'email') {
            Log::info('Modèle e-mail introuvable ou non publié.', [
                'employee_id' => $employee->id,
                'template_code' => $code,
                'locale' => $locale,
            ]);

            return null;
        }

        $employee->loadMissing('company');
        $context = $this->contextBuilder->build($employee, extra: $extra);
        $subjectRaw = $template->subject ?: $template->name;
        $subject = $this->renderer->render($subjectRaw, $context['flat']);
        $body = $this->renderer->render($template->body_html, $context['flat']);

        return new TemplatedMailNotification($subject, $body);
    }

    public function sendByTemplateCode(Employee $employee, string $templateCode, ?string $locale = null): bool
    {
        if (blank($employee->email) || $employee->company_id === null) {
            return false;
        }

        $locale ??= (string) config('document_templates.default_locale', 'fr');
        $companyId = (int) $employee->company_id;

        $template = $this->templates->findPublishedByCode($companyId, $templateCode, $locale);
        if ($template === null || $template->channel !== 'email') {
            Log::info('Modèle e-mail introuvable ou non publié.', [
                'employee_id' => $employee->id,
                'template_code' => $templateCode,
                'locale' => $locale,
            ]);

            return false;
        }

        try {
            $employee->loadMissing('company');
            $context = $this->contextBuilder->build($employee);
            $subjectRaw = $template->subject ?: $template->name;
            $subject = $this->renderer->render($subjectRaw, $context['flat']);
            $body = $this->renderer->render($template->body_html, $context['flat']);

            Notification::route('mail', $employee->email)->notify(
                new TemplatedMailNotification($subject, $body),
            );

            GeneratedDocument::query()->create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'document_template_id' => $template->id,
                'template_code' => $template->code,
                'template_version' => $template->version,
                'name' => $subject,
                'file_path' => null,
                'payload_snapshot' => $context['tree'],
                'employee_document_id' => null,
                'created_by' => null,
            ]);

            $this->audit->log('document_template.email_sent', $employee, [
                'employee_id' => $employee->id,
                'template_code' => $template->code,
                'email' => $employee->email,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Envoi e-mail modèle impossible.', [
                'employee_id' => $employee->id,
                'template_code' => $templateCode,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
