<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeeImportExportService
{
    private const HEADERS = [
        'matricule',
        'first_name',
        'last_name',
        'email',
        'phone',
        'department',
        'job_title',
        'contract_type',
        'hired_at',
        'is_active',
    ];

    public function __construct(
        private readonly EmployeeService $employees,
    ) {
    }

    /**
     * @param  list<int>  $ids
     */
    public function exportCsvForActor(User $actor, array $ids = []): StreamedResponse
    {
        $filters = [];
        $list = $this->employees->listForActor($actor, $filters);

        if ($ids !== []) {
            $idSet = array_fill_keys(array_map('intval', $ids), true);
            $list = $list->filter(static fn (Employee $e) => isset($idSet[(int) $e->id]));
        }

        $filename = 'employees-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($list) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // BOM UTF-8 pour Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADERS, ';');

            foreach ($list as $employee) {
                fputcsv($out, [
                    $employee->matricule,
                    $employee->first_name,
                    $employee->last_name,
                    $employee->email,
                    $employee->phone,
                    $employee->department,
                    $employee->job_title,
                    $employee->contract_type,
                    optional($employee->hired_at)->toDateString(),
                    $employee->is_active ? '1' : '0',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{imported: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function importCsvForActor(User $actor, UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return [
                'imported' => 0,
                'failed' => 1,
                'errors' => [['row' => 0, 'message' => 'Fichier illisible.']],
            ];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [
                'imported' => 0,
                'failed' => 1,
                'errors' => [['row' => 0, 'message' => 'Impossible d\'ouvrir le fichier.']],
            ];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return [
                'imported' => 0,
                'failed' => 1,
                'errors' => [['row' => 0, 'message' => 'Fichier vide.']],
            ];
        }

        // Retirer BOM éventuel
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        $headers = str_getcsv(trim($firstLine), $delimiter);
        $headers = array_map(static fn ($h) => strtolower(trim((string) $h)), $headers);

        $imported = 0;
        $failed = 0;
        /** @var list<array{row: int, message: string}> $errors */
        $errors = [];
        $rowNum = 1;

        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;

            if ($this->rowIsEmpty($cells)) {
                continue;
            }

            $raw = $this->mapRow($headers, $cells);
            if ($raw === null) {
                $failed++;
                $errors[] = ['row' => $rowNum, 'message' => 'Ligne invalide (colonnes manquantes).'];
                continue;
            }

            try {
                DB::transaction(function () use ($actor, $raw) {
                    $this->employees->createForActor($actor, array_merge($raw, [
                        'create_user' => false,
                    ]));
                });
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $message = $e->getMessage();
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $message = collect($e->errors())->flatten()->implode(' ');
                }
                $errors[] = ['row' => $rowNum, 'message' => $message];
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    /**
     * @param  list<string|null>  $headers
     * @param  list<string|null>  $cells
     * @return array<string, mixed>|null
     */
    private function mapRow(array $headers, array $cells): ?array
    {
        $data = [];
        foreach ($headers as $i => $header) {
            if ($header === '' || $header === null) {
                continue;
            }
            $data[$header] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
        }

        $matricule = $data['matricule'] ?? $data['employee_code'] ?? '';
        $firstName = $data['first_name'] ?? $data['firstName'] ?? '';
        $lastName = $data['last_name'] ?? $data['lastName'] ?? '';
        $email = $data['email'] ?? '';

        if ($matricule === '' || $firstName === '' || $lastName === '' || $email === '') {
            return null;
        }

        $isActiveRaw = strtolower((string) ($data['is_active'] ?? $data['active'] ?? '1'));
        $isActive = ! in_array($isActiveRaw, ['0', 'false', 'no', 'non', 'inactive'], true);

        $hiredAt = $data['hired_at'] ?? $data['join_date'] ?? null;
        if (is_string($hiredAt) && $hiredAt === '') {
            $hiredAt = null;
        }

        return [
            'matricule' => $matricule,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => ($data['phone'] ?? '') !== '' ? $data['phone'] : null,
            'department' => ($data['department'] ?? '') !== '' ? $data['department'] : null,
            'job_title' => ($data['job_title'] ?? $data['role'] ?? '') !== ''
                ? ($data['job_title'] ?? $data['role'] ?? null)
                : null,
            'contract_type' => ($data['contract_type'] ?? '') !== '' ? $data['contract_type'] : 'CDI',
            'hired_at' => $hiredAt,
            'is_active' => $isActive,
        ];
    }

    /** @param  list<string|null>  $cells */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
