<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\EmployeeProfileLinker;
use App\Models\User;
use Illuminate\Console\Command;

final class LinkStaffUsersToEmployeesCommand extends Command
{
    protected $signature = 'hr:link-staff-employees {--company= : Limiter à une company_id}';

    protected $description = 'Crée une fiche Employee pour les users admin/team_manager/employe qui n\'en ont pas';

    public function handle(EmployeeProfileLinker $linker): int
    {
        $query = User::query()
            ->whereIn('role', EmployeeProfileLinker::STAFF_ROLES)
            ->whereNotNull('company_id')
            ->whereNotNull('email');

        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }

        $created = 0;
        $existing = 0;
        $failed = 0;

        foreach ($query->cursor() as $user) {
            try {
                $before = $linker->findForUser($user);
                $employee = $linker->findOrCreateForUser($user);
                if ($before === null && $employee !== null) {
                    $created++;
                    $this->line("Créé : {$user->email} → employé #{$employee->id} ({$employee->matricule})");
                } else {
                    $existing++;
                    if ($employee !== null) {
                        $this->line("Soldes vérifiés : {$user->email} → employé #{$employee->id}");
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Échec {$user->email} : ".$e->getMessage());
            }
        }

        $this->info("Terminé — créés: {$created}, déjà liés: {$existing}, échecs: {$failed}");

        return self::SUCCESS;
    }
}
