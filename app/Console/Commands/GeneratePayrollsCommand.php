<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\PayrollService;
use Illuminate\Console\Command;

final class GeneratePayrollsCommand extends Command
{
    protected $signature = 'hr:generate-payrolls
                            {--company= : Limiter à une company_id}
                            {--period= : Période YYYY-MM (défaut : mois courant si la période est close)}
                            {--dry-run : Calcule sans persister}
                            {--force : Régénère les bulletins non validés / non payés}
                            {--include-manual : Inclure les sociétés en génération manuelle}';

    protected $description = 'Génère les bulletins de paie des sociétés en génération automatique';

    public function handle(PayrollService $service): int
    {
        $period = $this->option('period');
        if (is_string($period) && $period !== '' && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Période invalide. Format attendu : YYYY-MM.');

            return self::FAILURE;
        }

        $reports = $service->generateDue([
            'company_id' => $this->option('company') ? (int) $this->option('company') : null,
            'period' => is_string($period) && $period !== '' ? $period : null,
            'dry_run' => (bool) $this->option('dry-run'),
            'force' => (bool) $this->option('force'),
            'include_manual' => (bool) $this->option('include-manual'),
        ]);

        if ($reports === []) {
            $this->warn('Aucune société à traiter (génération auto inactive, période non close, ou filtre trop restrictif).');

            return self::SUCCESS;
        }

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($reports as $report) {
            $generated += (int) $report['generated'];
            $skipped += (int) $report['skipped'];
            $failed += (int) $report['failed'];

            $this->info(sprintf(
                'Société #%d — période %s : générés %d, ignorés %d, échecs %d',
                $report['company_id'],
                $report['period'],
                $report['generated'],
                $report['skipped'],
                $report['failed'],
            ));

            foreach ($report['details'] as $row) {
                $label = sprintf('#%d %s', $row['employee_id'], $row['matricule'] ?? '');
                $reason = $row['reason'] ? ' — '.$row['reason'] : '';
                match ($row['status']) {
                    'failed' => $this->error("  {$row['status']} {$label}{$reason}"),
                    'skipped' => $this->line("  {$row['status']} {$label}{$reason}"),
                    default => $this->line("  {$row['status']} {$label}"),
                };
            }
        }

        $this->newLine();
        $this->info("Terminé — générés: {$generated}, ignorés: {$skipped}, échecs: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
