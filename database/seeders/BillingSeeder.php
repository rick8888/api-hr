<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Services\InvoiceService;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Company\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/billing.json');

        if (! File::exists($path)) {
            return;
        }

        /** @var array{invoices: array<int, array<string, mixed>>} $data */
        $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        /** @var InvoiceService $service */
        $service = app(InvoiceService::class);

        foreach ($data['invoices'] as $row) {
            $company = Company::query()->where('code', $row['company_code'])->first();

            if ($company === null) {
                continue;
            }

            $payments = $row['payments'] ?? [];
            $targetStatus = (string) ($row['status'] ?? Invoice::STATUS_DRAFT);
            unset($row['company_code'], $row['payments']);

            $seedStatus = $payments !== []
                ? Invoice::STATUS_PENDING
                : $targetStatus;

            $invoice = Invoice::query()->updateOrCreate(
                ['invoice_number' => $row['invoice_number']],
                [
                    'company_id' => $company->id,
                    'plan_code' => $row['plan_code'] ?? null,
                    'description' => $row['description'],
                    'amount' => $row['amount'],
                    'currency' => $row['currency'] ?? $company->currency,
                    'status' => $seedStatus,
                    'issue_date' => $row['issue_date'],
                    'due_date' => $row['due_date'],
                    'period_start' => $row['period_start'] ?? null,
                    'period_end' => $row['period_end'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'amount_paid' => 0,
                    'paid_at' => null,
                ]
            );

            $invoice->payments()->delete();

            if ($payments !== []) {
                foreach ($payments as $payment) {
                    $service->recordPayment($invoice->fresh(), $payment);
                }
            } else {
                $status = $targetStatus;
                if ($status === Invoice::STATUS_PENDING && $row['due_date'] < now()->toDateString()) {
                    $status = Invoice::STATUS_OVERDUE;
                }
                $invoice->update([
                    'status' => $status,
                    'amount_paid' => 0,
                    'paid_at' => null,
                ]);
            }
        }
    }
}
