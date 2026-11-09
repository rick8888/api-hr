<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\PerformanceObjectiveReminderService;
use Illuminate\Console\Command;

class SendPerformanceObjectiveReminders extends Command
{
    protected $signature = 'hr:send-objective-reminders';

    protected $description = 'Envoie les rappels des échéances d’objectifs de performance.';

    public function handle(PerformanceObjectiveReminderService $service): int
    {
        $sent = $service->sendDueReminders();
        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
