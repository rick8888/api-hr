<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\WhatsAppAttendanceBotService;
use App\Application\Services\WhatsAppSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook public Meta WhatsApp Cloud API.
 * GET  → vérification du challenge
 * POST → messages entrants (pointage)
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppSettingsService $settings,
        private readonly WhatsAppAttendanceBotService $bot,
    ) {
    }

    public function verify(Request $request): Response
    {
        $mode = (string) (
            $request->query('hub_mode')
            ?? data_get($request->query(), 'hub.mode')
            ?? $request->input('hub.mode')
            ?? ''
        );
        $token = (string) (
            $request->query('hub_verify_token')
            ?? data_get($request->query(), 'hub.verify_token')
            ?? $request->input('hub.verify_token')
            ?? ''
        );
        $challenge = (string) (
            $request->query('hub_challenge')
            ?? data_get($request->query(), 'hub.challenge')
            ?? $request->input('hub.challenge')
            ?? ''
        );

        // Meta envoie aussi hub[mode] qui devient un tableau hub
        if ($mode === '' || $token === '') {
            $hub = $request->query('hub');
            if (is_array($hub)) {
                $mode = (string) ($hub['mode'] ?? $mode);
                $token = (string) ($hub['verify_token'] ?? $token);
                $challenge = (string) ($hub['challenge'] ?? $challenge);
            }
        }

        if (($mode === 'subscribe' || $mode === '') && $token !== '') {
            $config = $this->settings->findConfigByVerifyToken($token);
            if ($config !== null) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }
        }

        Log::warning('whatsapp.webhook.verify_failed', [
            'mode' => $mode,
            'token_present' => $token !== '',
        ]);

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        // Vérification signature optionnelle si app_secret renseigné
        $payload = $request->all();
        $entries = $payload['entry'] ?? [];

        if (! is_array($entries)) {
            return response('OK', 200);
        }

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            if (! is_array($changes)) {
                continue;
            }
            foreach ($changes as $change) {
                $value = $change['value'] ?? null;
                if (! is_array($value)) {
                    continue;
                }
                // signature soft-check per phone_number_id config
                $phoneNumberId = (string) data_get($value, 'metadata.phone_number_id', '');
                $config = $this->settings->findConfigByPhoneNumberId($phoneNumberId);
                if ($config && ! empty($config['app_secret'])) {
                    if (! $this->validSignature($request, (string) $config['app_secret'])) {
                        Log::warning('whatsapp.webhook.invalid_signature', [
                            'phone_number_id' => $phoneNumberId,
                        ]);
                        continue;
                    }
                }
                $this->bot->handleInboundValue($value);
            }
        }

        // Meta exige 200 rapide
        return response('OK', 200);
    }

    private function validSignature(Request $request, string $appSecret): bool
    {
        $header = (string) $request->header('X-Hub-Signature-256', '');
        if ($header === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $header);
    }
}
