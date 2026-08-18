<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\DTO\Compliance\ComplianceObligationData;
use App\Domain\Company\Models\Country;
use App\Domain\Compliance\Models\ComplianceObligation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ComplianceSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/compliance-obligations.json');

        if (! File::exists($path)) {
            return;
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $country = Country::query()->where('code', $row['country_code'])->first();

            if ($country === null) {
                continue;
            }

            $data = ComplianceObligationData::fromArray([
                'country_id' => $country->id,
                'code' => $row['code'],
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'type' => $row['type'] ?? ComplianceObligation::TYPE_SOCIAL,
                'authority' => $row['authority'] ?? null,
                'frequency' => $row['frequency'] ?? ComplianceObligation::FREQUENCY_MONTHLY,
                'reference_period' => $row['reference_period'] ?? ComplianceObligation::REF_PREVIOUS_MONTH,
                'due_rule' => $row['due_rule'] ?? null,
                'grace_days' => $row['grace_days'] ?? 0,
                'effective_from' => $row['effective_from'] ?? null,
                'is_active' => $row['is_active'] ?? true,
                'settings' => $row['settings'] ?? null,
            ]);

            ComplianceObligation::query()->updateOrCreate(
                ['country_id' => $country->id, 'code' => $data->toModelArray()['code']],
                $data->toModelArray(),
            );
        }
    }
}
