<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Configuration WhatsApp Business Cloud API, stockée dans companies.settings.whatsapp.
 */
final class WhatsAppSettingsService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** @return array<string, mixed> */
    public function getForActor(User $actor): array
    {
        return $this->format($this->resolveCompany($actor));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateForActor(User $actor, array $data): array
    {
        $company = $this->resolveCompany($actor);

        return DB::transaction(function () use ($company, $data) {
            $settings = is_array($company->settings) ? $company->settings : [];
            $whatsapp = is_array($settings['whatsapp'] ?? null) ? $settings['whatsapp'] : [];

            $next = array_merge($whatsapp, $this->normalize($data, $whatsapp));

            // Générer un verify_token si absent
            if (empty($next['verify_token'])) {
                $next['verify_token'] = 'bravo_wa_'.Str::random(32);
            }

            $settings['whatsapp'] = $next;
            $company->settings = $settings;
            $company->save();

            $this->audit->log('whatsapp_settings.updated', $company, [
                'enabled' => (bool) ($next['enabled'] ?? false),
                'attendance_enabled' => (bool) ($next['attendance_enabled'] ?? false),
                'phone_number_id' => $next['phone_number_id'] ?? null,
            ]);

            return $this->format($company->fresh());
        });
    }

    /** @return array<string, mixed>|null */
    public function findConfigByPhoneNumberId(string $phoneNumberId): ?array
    {
        if ($phoneNumberId === '') {
            return null;
        }

        $company = Company::query()
            ->where('settings->whatsapp->phone_number_id', $phoneNumberId)
            ->first();

        if ($company === null) {
            // fallback si le JSON path n'est pas indexé partout
            $company = Company::query()
                ->get()
                ->first(function (Company $c) use ($phoneNumberId) {
                    $cfg = is_array($c->settings['whatsapp'] ?? null) ? $c->settings['whatsapp'] : [];

                    return (string) ($cfg['phone_number_id'] ?? '') === $phoneNumberId;
                });
        }

        if ($company === null) {
            return null;
        }

        $cfg = is_array($company->settings['whatsapp'] ?? null) ? $company->settings['whatsapp'] : [];
        $cfg['company_id'] = $company->id;

        return $cfg;
    }

    /** @return array<string, mixed>|null */
    public function findConfigByVerifyToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $company = Company::query()
            ->get()
            ->first(function (Company $c) use ($token) {
                $cfg = is_array($c->settings['whatsapp'] ?? null) ? $c->settings['whatsapp'] : [];

                return (string) ($cfg['verify_token'] ?? '') === $token;
            });

        if ($company === null) {
            return null;
        }

        $cfg = is_array($company->settings['whatsapp'] ?? null) ? $company->settings['whatsapp'] : [];
        $cfg['company_id'] = $company->id;

        return $cfg;
    }

    private function resolveCompany(User $actor): Company
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin'], true)) {
            throw new AccessDeniedHttpException(
                'Accès non autorisé. Compte admin/tenant avec entreprise requis.'
            );
        }

        return Company::query()->findOrFail($actor->company_id);
    }

    /** @return array<string, mixed> */
    private function format(Company $company): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $whatsapp = is_array($settings['whatsapp'] ?? null) ? $settings['whatsapp'] : [];

        return [
            'company_id' => $company->id,
            'enabled' => (bool) ($whatsapp['enabled'] ?? false),
            'attendance_enabled' => (bool) ($whatsapp['attendance_enabled'] ?? false),
            'provider' => (string) ($whatsapp['provider'] ?? 'meta_cloud'),
            'api_version' => (string) ($whatsapp['api_version'] ?? 'v21.0'),
            'phone_number_id' => (string) ($whatsapp['phone_number_id'] ?? ''),
            'business_account_id' => (string) ($whatsapp['business_account_id'] ?? ''),
            'display_phone' => (string) ($whatsapp['display_phone'] ?? ''),
            'verify_token' => (string) ($whatsapp['verify_token'] ?? ''),
            'access_token_set' => ! empty($whatsapp['access_token']),
            'app_secret_set' => ! empty($whatsapp['app_secret']),
            // secrets never returned plain
            'access_token' => '',
            'app_secret' => '',
            'welcome_message' => (string) ($whatsapp['welcome_message'] ?? $this->defaultWelcome()),
            'webhook_url' => url('/api/v1/webhooks/whatsapp'),
            'commands' => [
                'ENTREE / IN / ARRIVEE' => 'Pointer l’entrée',
                'SORTIE / OUT / DEPART' => 'Pointer la sortie',
                'STATUT / STATUS' => 'Voir le pointage du jour',
                'AIDE / HELP' => 'Liste des commandes',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function normalize(array $data, array $current): array
    {
        $out = [];

        $boolKeys = ['enabled', 'attendance_enabled'];
        foreach ($boolKeys as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = filter_var($data[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $stringKeys = [
            'provider',
            'api_version',
            'phone_number_id',
            'business_account_id',
            'display_phone',
            'verify_token',
            'welcome_message',
        ];
        foreach ($stringKeys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = trim((string) $data[$key]);
            $out[$key] = $value === '' ? null : $value;
        }

        // Secrets : garder l’existant si vide
        foreach (['access_token', 'app_secret'] as $secretKey) {
            if (! array_key_exists($secretKey, $data)) {
                continue;
            }
            $value = trim((string) $data[$secretKey]);
            if ($value === '') {
                continue;
            }
            $out[$secretKey] = $value;
        }

        if (empty($out['provider'] ?? $current['provider'] ?? null)) {
            $out['provider'] = 'meta_cloud';
        }
        if (empty($out['api_version'] ?? $current['api_version'] ?? null)) {
            $out['api_version'] = 'v21.0';
        }

        return $out;
    }

    private function defaultWelcome(): string
    {
        return "Bonjour ! Pour pointer via WhatsApp :\n"
            ."• ENTREE — pointer l’arrivée\n"
            ."• SORTIE — pointer le départ\n"
            ."• STATUT — voir le pointage du jour\n"
            .'• AIDE — ces commandes';
    }
}
