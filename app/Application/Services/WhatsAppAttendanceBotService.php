<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\Shift;
use App\Domain\Attendance\Repositories\AttendanceRecordRepositoryInterface;
use App\Domain\Employee\Models\Employee;
use App\Infrastructure\Integrations\WhatsApp\WhatsAppCloudClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Bot WhatsApp : interprète les messages et pilote le pointage employé.
 *
 * Commandes (FR + EN) :
 *  ENTREE | IN | ARRIVEE | PRESENCE
 *  SORTIE | OUT | DEPART
 *  STATUT | STATUS
 *  AIDE | HELP | MENU
 */
final class WhatsAppAttendanceBotService
{
    public function __construct(
        private readonly WhatsAppSettingsService $settings,
        private readonly WhatsAppCloudClient $client,
        private readonly AttendanceRecordRepositoryInterface $records,
        private readonly AuditLogger $audit,
        private readonly AttendanceMetricsCalculator $metrics,
    ) {}

    /**
     * Traite un payload webhook Meta (value.messages).
     *
     * @param  array<string, mixed>  $value
     */
    public function handleInboundValue(array $value): void
    {
        $phoneNumberId = (string) data_get($value, 'metadata.phone_number_id', '');
        $config = $this->settings->findConfigByPhoneNumberId($phoneNumberId);

        if ($config === null || empty($config['enabled'])) {
            Log::info('whatsapp.webhook.unknown_or_disabled', ['phone_number_id' => $phoneNumberId]);

            return;
        }

        $messages = $value['messages'] ?? [];
        if (! is_array($messages) || $messages === []) {
            return;
        }

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }
            try {
                $this->handleMessage($config, $message);
            } catch (Throwable $e) {
                Log::error('whatsapp.bot.message_error', [
                    'error' => $e->getMessage(),
                    'company_id' => $config['company_id'] ?? null,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(array $config, array $message): void
    {
        $from = (string) ($message['from'] ?? '');
        $type = (string) ($message['type'] ?? '');
        $text = '';

        if ($type === 'text') {
            $text = (string) data_get($message, 'text.body', '');
        } elseif ($type === 'button') {
            $text = (string) data_get($message, 'button.text', '');
        } elseif ($type === 'interactive') {
            $text = (string) (
                data_get($message, 'interactive.button_reply.title')
                ?? data_get($message, 'interactive.list_reply.title')
                ?? ''
            );
        }

        $text = trim($text);
        if ($from === '' || $text === '') {
            return;
        }

        $reply = $this->processCommand($config, $from, $text);

        try {
            $this->client->sendText($config, $from, $reply);
        } catch (Throwable $e) {
            Log::error('whatsapp.bot.reply_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function processCommand(array $config, string $fromPhone, string $text): string
    {
        $companyId = (int) ($config['company_id'] ?? 0);
        if ($companyId <= 0) {
            return 'Configuration WhatsApp incomplète.';
        }

        if (empty($config['attendance_enabled'])) {
            return 'Le pointage WhatsApp n’est pas activé pour votre entreprise. Contactez les RH.';
        }

        $command = $this->normalizeCommand($text);

        if (in_array($command, ['AIDE', 'HELP', 'MENU', 'START', 'BONJOUR', 'HI', 'HELLO'], true)) {
            return (string) ($config['welcome_message'] ?? $this->defaultHelp());
        }

        $employee = $this->findEmployeeByPhone($companyId, $fromPhone);
        if ($employee === null) {
            return "Numéro non reconnu.\n"
                .'Vérifiez que votre téléphone mobile est bien renseigné sur votre fiche employé (même numéro WhatsApp).';
        }

        if (! $employee->is_active) {
            return 'Votre compte employé est inactif. Contactez les RH.';
        }

        return match ($command) {
            'ENTREE', 'IN', 'ARRIVEE', 'ARRIVÉE', 'PRESENCE', 'PRÉSENCE', 'CHECKIN', 'CHECK_IN' => $this->doCheckIn($employee),
            'SORTIE', 'OUT', 'DEPART', 'DÉPART', 'CHECKOUT', 'CHECK_OUT' => $this->doCheckOut($employee),
            'STATUT', 'STATUS', 'INFO' => $this->doStatus($employee),
            default => "Commande non reconnue : « {$text} »\n\n".$this->defaultHelp(),
        };
    }

    private function doCheckIn(Employee $employee): string
    {
        try {
            $now = Carbon::now()->format('H:i');
            $today = Carbon::today()->toDateString();
            $existing = $this->todayRecord((int) $employee->company_id, (int) $employee->id, $today);
            $openRecord = $this->openRecord((int) $employee->company_id, (int) $employee->id);

            if ($openRecord !== null && ($existing === null || $openRecord->isNot($existing))) {
                return '❌ Terminez le pointage précédent avant de commencer une nouvelle journée.';
            }

            if ($existing !== null && filled($existing->check_in)) {
                $in = substr((string) $existing->check_in, 0, 5);

                return "✅ Déjà pointé à l’entrée à {$in}.";
            }

            $shiftId = $existing?->shift_id ?? $this->assignedShiftId($employee);
            DB::transaction(function () use ($employee, $today, $now, $existing, $shiftId) {
                $payload = $this->withMetrics([
                    'company_id' => (int) $employee->company_id,
                    'employee_id' => (int) $employee->id,
                    'attendance_date' => $today,
                    'shift_id' => $shiftId,
                    'site_id' => $existing?->site_id,
                    'check_in' => $now,
                    'check_out' => null,
                    'status' => in_array($existing?->status, ['absent', 'leave'], true)
                        ? 'on_time'
                        : ($existing?->status ?: 'on_time'),
                ]);

                if ($existing !== null) {
                    $record = $this->records->update($existing, $payload);
                } else {
                    $record = $this->records->create($payload);
                }

                $this->audit->log('attendance_record.check_in_whatsapp', $record, [
                    'employee_id' => $record->employee_id,
                    'channel' => 'whatsapp',
                ]);
            });

            $name = trim($employee->first_name.' '.$employee->last_name);

            return "✅ Entrée enregistrée pour {$name} à {$now}.\nBonne journée !";
        } catch (ValidationException $e) {
            return '❌ '.$this->firstValidationMessage($e);
        } catch (Throwable $e) {
            Log::error('whatsapp.check_in_failed', ['error' => $e->getMessage()]);

            return '❌ Impossible d’enregistrer l’entrée. Réessayez ou contactez les RH.';
        }
    }

    private function doCheckOut(Employee $employee): string
    {
        try {
            $now = Carbon::now()->format('H:i');
            $today = Carbon::today()->toDateString();
            $todayRecord = $this->todayRecord((int) $employee->company_id, (int) $employee->id, $today);
            if ($todayRecord !== null && filled($todayRecord->check_out)) {
                $out = substr((string) $todayRecord->check_out, 0, 5);

                return "✅ Déjà pointé en sortie à {$out}.";
            }
            $existing = $this->openRecord((int) $employee->company_id, (int) $employee->id);

            if ($existing === null || blank($existing->check_in)) {
                return '❌ Vous devez d’abord pointer l’entrée (envoyez ENTREE).';
            }
            DB::transaction(function () use ($existing, $now) {
                $payload = $this->withMetrics([
                    'company_id' => (int) $existing->company_id,
                    'employee_id' => (int) $existing->employee_id,
                    'attendance_date' => $existing->attendance_date?->toDateString(),
                    'shift_id' => $existing->shift_id,
                    'site_id' => $existing->site_id,
                    'check_in' => $existing->check_in ? substr((string) $existing->check_in, 0, 5) : null,
                    'check_out' => $now,
                    'status' => $existing->status ?: 'on_time',
                ]);

                $record = $this->records->update($existing, $payload);
                $this->audit->log('attendance_record.check_out_whatsapp', $record, [
                    'employee_id' => $record->employee_id,
                    'channel' => 'whatsapp',
                ]);
            });

            return "✅ Sortie enregistrée à {$now}.\nÀ demain !";
        } catch (Throwable $e) {
            Log::error('whatsapp.check_out_failed', ['error' => $e->getMessage()]);

            return '❌ Impossible d’enregistrer la sortie. Réessayez ou contactez les RH.';
        }
    }

    private function doStatus(Employee $employee): string
    {
        $today = Carbon::today()->toDateString();
        $record = $this->todayRecord((int) $employee->company_id, (int) $employee->id, $today);
        $record ??= $this->openRecord((int) $employee->company_id, (int) $employee->id);
        $name = trim($employee->first_name.' '.$employee->last_name);

        if ($record === null) {
            return "📋 {$name} — {$today}\nAucun pointage aujourd’hui.";
        }

        $in = $record->check_in ? substr((string) $record->check_in, 0, 5) : '—';
        $out = $record->check_out ? substr((string) $record->check_out, 0, 5) : '—';
        $status = (string) ($record->status ?: '—');

        $recordDate = $record->attendance_date?->toDateString() ?? $today;

        return "📋 {$name} — {$recordDate}\nEntrée : {$in}\nSortie : {$out}\nStatut : {$status}";
    }

    private function findEmployeeByPhone(int $companyId, string $fromPhone): ?Employee
    {
        $needle = $this->digits($fromPhone);
        if (strlen($needle) < 8) {
            return null;
        }

        $candidates = Employee::query()
            ->where('company_id', $companyId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        foreach ($candidates as $employee) {
            $empDigits = $this->digits((string) $employee->phone);
            if (strlen($empDigits) < 8) {
                continue;
            }
            if ($empDigits === $needle) {
                return $employee;
            }
            // Comparaison sur les 9 derniers chiffres (local vs E.164)
            if (str_ends_with($empDigits, substr($needle, -9))
                || str_ends_with($needle, substr($empDigits, -9))) {
                return $employee;
            }
        }

        return null;
    }

    private function digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function normalizeCommand(string $text): string
    {
        $t = mb_strtoupper(trim($text));
        $t = str_replace(['É', 'È', 'Ê', 'Ë'], 'E', $t);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;
        // first word if phrase
        $parts = explode(' ', $t);

        return $parts[0] ?? $t;
    }

    private function todayRecord(int $companyId, int $employeeId, string $today): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $today)
            ->first();
    }

    private function openRecord(int $companyId, int $employeeId): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', '>=', Carbon::yesterday()->toDateString())
            ->whereDate('attendance_date', '<=', Carbon::today()->toDateString())
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->orderByDesc('attendance_date')
            ->first();
    }

    private function assignedShiftId(Employee $employee): ?int
    {
        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        $shiftId = $metadata['shift_id'] ?? null;
        if ($shiftId === null || $shiftId === '') {
            return null;
        }

        $shift = Shift::query()
            ->whereKey((int) $shiftId)
            ->where('company_id', $employee->company_id)
            ->first();
        if ($shift === null) {
            throw ValidationException::withMessages([
                'shift' => 'Le shift affecté à votre profil est introuvable pour cette société.',
            ]);
        }

        return (int) $shift->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withMetrics(array $payload): array
    {
        $shift = isset($payload['shift_id']) ? Shift::query()->find($payload['shift_id']) : null;

        return $this->metrics->calculate($payload, $shift);
    }

    private function firstValidationMessage(ValidationException $e): string
    {
        $messages = $e->errors();
        foreach ($messages as $list) {
            if (is_array($list) && isset($list[0])) {
                return (string) $list[0];
            }
        }

        return 'Données invalides.';
    }

    private function defaultHelp(): string
    {
        return "Commandes disponibles :\n"
            ."• ENTREE — pointer l’arrivée\n"
            ."• SORTIE — pointer le départ\n"
            ."• STATUT — voir le pointage du jour\n"
            .'• AIDE — cette aide';
    }
}
