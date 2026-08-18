<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client HTTP pour WhatsApp Cloud API (Meta Graph).
 */
final class WhatsAppCloudClient
{
    /**
     * @param  array<string, mixed>  $config  config entreprise (access_token, phone_number_id, api_version)
     */
    public function sendText(array $config, string $toE164Digits, string $body): void
    {
        $token = (string) ($config['access_token'] ?? '');
        $phoneNumberId = (string) ($config['phone_number_id'] ?? '');
        $version = (string) ($config['api_version'] ?? 'v21.0');

        if ($token === '' || $phoneNumberId === '') {
            throw new RuntimeException('WhatsApp non configuré (token ou phone_number_id manquant).');
        }

        $to = preg_replace('/\D+/', '', $toE164Digits) ?? '';
        if ($to === '') {
            throw new RuntimeException('Numéro destinataire invalide.');
        }

        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('whatsapp.send_failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
            throw new RuntimeException('Échec d’envoi WhatsApp : '.$response->status());
        }
    }
}
