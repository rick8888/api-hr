<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Country;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CountryService
{
    /** @return Collection<int, Country> */
    public function list(bool $onlyActive = false): Collection
    {
        return Country::query()
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Country
    {
        return DB::transaction(function () use ($data) {
            return Country::query()->create([
                'code' => strtoupper(trim((string) $data['code'])),
                'name' => trim((string) $data['name']),
                'currency' => strtoupper(trim((string) $data['currency'])),
                'locale' => trim((string) ($data['locale'] ?? 'fr_FR')),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'settings' => $data['settings'] ?? null,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Country $country, array $data): Country
    {
        return DB::transaction(function () use ($country, $data) {
            $payload = [];

            if (array_key_exists('code', $data)) {
                $payload['code'] = strtoupper(trim((string) $data['code']));
            }
            if (array_key_exists('name', $data)) {
                $payload['name'] = trim((string) $data['name']);
            }
            if (array_key_exists('currency', $data)) {
                $payload['currency'] = strtoupper(trim((string) $data['currency']));
            }
            if (array_key_exists('locale', $data)) {
                $payload['locale'] = trim((string) $data['locale']);
            }
            if (array_key_exists('is_active', $data)) {
                $payload['is_active'] = (bool) $data['is_active'];
            }
            if (array_key_exists('settings', $data)) {
                $payload['settings'] = $data['settings'];
            }

            $country->update($payload);

            return $country->fresh();
        });
    }

    public function toggleStatus(Country $country, bool $isActive): Country
    {
        $country->update(['is_active' => $isActive]);

        return $country->fresh();
    }

    public function delete(Country $country): void
    {
        if ($country->companies()->exists()) {
            throw ValidationException::withMessages([
                'country' => 'Ce pays est lié à des entreprises et ne peut pas être supprimé.',
            ]);
        }

        if ($country->legalTaxes()->exists()) {
            throw ValidationException::withMessages([
                'country' => 'Ce pays est lié à des cotisations fiscales et ne peut pas être supprimé.',
            ]);
        }

        $country->delete();
    }
}
