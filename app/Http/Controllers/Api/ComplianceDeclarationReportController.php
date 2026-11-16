<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\ComplianceDeclarationReportService;
use App\Application\Services\AuditLogger;
use App\Application\Services\TenantCompanyScope;
use App\Domain\Company\Models\Company;
use App\Domain\Compliance\Models\ComplianceObligation;
use App\Domain\Compliance\Models\Declaration;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ComplianceDeclarationReportController extends Controller
{
    public function __construct(
        private readonly ComplianceDeclarationReportService $reportService,
        private readonly TenantCompanyScope $tenantScope,
        private readonly AuditLogger $audit,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $data = $this->buildReport($request);

        return response()->json(['data' => $data]);
    }

    public function downloadPdf(Request $request): Response
    {
        $data = $this->buildReport($request);
        $filename = sprintf(
            'rapport-declarations-%s-%s.pdf',
            preg_replace('/[^A-Za-z0-9._-]/', '_', $data['company']['name']),
            $data['period'],
        );

        return Pdf::loadView('reports.compliance-declarations', ['report' => $data])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->download($filename);
    }

    public function updatePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'obligation_id' => ['required', 'integer', 'exists:compliance_obligations,id'],
            'is_paid' => ['required', 'boolean'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);
        $companyId = $this->tenantScope->companyIdFor(
            $request->user(),
            isset($validated['company_id']) ? (int) $validated['company_id'] : null,
        );

        if ($companyId === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Une entreprise doit être sélectionnée.');
        }

        $company = Company::query()->findOrFail($companyId);
        $this->tenantScope->ensureOwnsCompany($request->user(), $company->id);
        $obligation = ComplianceObligation::query()
            ->whereKey($validated['obligation_id'])
            ->where('country_id', $company->country_id)
            ->where('is_active', true)
            ->firstOrFail();

        $declaration = DB::transaction(function () use ($validated, $company, $obligation, $request) {
            $declaration = Declaration::query()->firstOrNew([
                'company_id' => $company->id,
                'compliance_obligation_id' => $obligation->id,
                'period' => $validated['period'],
            ]);

            if ($validated['is_paid']) {
                $declaration->paid_at = now();
                $declaration->paid_by = $request->user()->id;
            } else {
                $declaration->paid_at = null;
                $declaration->paid_by = null;
            }

            $declaration->save();
            $this->audit->log(
                $validated['is_paid'] ? 'compliance_declaration.payment_marked' : 'compliance_declaration.payment_unmarked',
                $declaration,
                [
                    'company_id' => $company->id,
                    'obligation_id' => $obligation->id,
                    'period' => $validated['period'],
                ],
                userId: $request->user()->id,
            );

            return $declaration->load('paidBy:id,name');
        });

        return response()->json([
            'data' => [
                'obligation_id' => $obligation->id,
                'period' => $declaration->period,
                'is_paid' => $declaration->paid_at !== null,
                'paid_at' => $declaration->paid_at?->toIso8601String(),
                'paid_by' => $declaration->paidBy?->name,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);
        $companyId = $this->tenantScope->companyIdFor(
            $request->user(),
            isset($validated['company_id']) ? (int) $validated['company_id'] : null,
        );

        if ($companyId === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Une entreprise doit être sélectionnée.');
        }

        $company = Company::query()->with('country')->findOrFail($companyId);
        $this->tenantScope->ensureOwnsCompany($request->user(), $company->id);

        return $this->reportService->build($company, $validated['period']);
    }
}
