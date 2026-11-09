<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\EmployeeProbationService;
use Illuminate\Console\Command;

class SendEmployeeProbationReminders extends Command
{
    protected $signature = 'hr:send-probation-reminders';

    protected $description = 'Envoie les rappels d’échéance des périodes d’essai aux responsables RH.';

    public function handle(EmployeeProbationService $service): int
    {
        $sent = $service->sendDueReminders();
        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
