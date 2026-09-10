<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Back\Trainee;
use App\Models\Back\WhatsAppMessage;
use App\Support\WhatsAppBroadcast;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TwilioWhatsAppService
{
    private const CONTENT_API = 'https://content.twilio.com/v1';

    private const MESSAGES_API = 'https://api.twilio.com/2010-04-01';

    private ?Client $client = null;

    private function client(): Client
    {
        if ($this->client === null) {
            $this->client = new Client([
                'auth' => [
                    config('twilio.account_sid'),
                    config('twilio.auth_token'),
                ],
                'http_errors' => false,
            ]);
        }

        return $this->client;
    }

    public function isConfigured(): bool
    {
        $hasCredentials = filled(config('twilio.account_sid'))
            && filled(config('twilio.auth_token'));

        $hasSender = filled(config('twilio.messaging_service_sid'))
            || filled(config('twilio.whatsapp_from'));

        return $hasCredentials && $hasSender;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        $response = $this->client()->get(self::CONTENT_API . '/Content', [
            'query' => ['PageSize' => 100],
        ]);

        $payload = json_decode((string) $response->getBody(), true);

        if ($response->getStatusCode() >= 400) {
            Log::error('Twilio Content API list failed', [
                'status' => $response->getStatusCode(),
                'response' => $payload,
            ]);

            throw new RuntimeException('Failed to fetch WhatsApp templates.');
        }

        $templates = [];

        foreach ($payload['contents'] ?? [] as $content) {
            $templates[] = $this->formatContentTemplate($content);
        }

        return $templates;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplate(string $contentSid): array
    {
        $response = $this->client()->get(self::CONTENT_API . '/Content/' . $contentSid);

        $payload = json_decode((string) $response->getBody(), true);

        if ($response->getStatusCode() >= 400) {
            Log::error('Twilio Content API fetch failed', [
                'status' => $response->getStatusCode(),
                'content_sid' => $contentSid,
                'response' => $payload,
            ]);

            throw new RuntimeException('Failed to fetch WhatsApp template.');
        }

        return array_merge(
            $this->formatContentTemplate($payload),
            ['approval_status' => $this->getApprovalStatus($contentSid)]
        );
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function formatContentTemplate(array $content): array
    {
        $body = $this->extractTemplateBody($content['types'] ?? []);
        $variables = $this->extractVariableKeys($body);
        $variableSamples = $content['variables'] ?? [];

        if ($variables === [] && is_array($variableSamples) && $variableSamples !== []) {
            $variables = array_map('strval', array_keys($variableSamples));
            sort($variables, SORT_NUMERIC);
        }

        return [
            'sid' => $content['sid'],
            'friendly_name' => $content['friendly_name'] ?? '',
            'language' => $content['language'] ?? '',
            'body' => $body,
            'variables' => array_values($variables),
            'variable_samples' => $variableSamples,
        ];
    }

    /**
     * @param  array<string, string>  $contentVariables
     * @return array<string, mixed>
     */
    public function sendTemplate(string $phone, string $contentSid, array $contentVariables = [], ?string $traineeId = null): array
    {
        $params = [
            'To' => $this->toWhatsAppAddress($phone),
            'ContentSid' => $contentSid,
        ];

        if ($contentVariables !== []) {
            $params['ContentVariables'] = json_encode($contentVariables, JSON_UNESCAPED_UNICODE);
        }

        $message = $this->sendMessageRequest($params);

        $this->storeOutboundMessage($message, $phone, $traineeId);

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function sendFreeformMessage(string $phone, string $body, ?string $traineeId = null): array
    {
        $message = $this->sendMessageRequest([
            'To' => $this->toWhatsAppAddress($phone),
            'Body' => $body,
        ]);

        $this->storeOutboundMessage($message, $phone, $traineeId);

        return $message;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessages(string $phone, int $limit = 30, ?string $since = null): array
    {
        $normalizedPhone = $this->normalizePhoneDigits($phone);
        $query = WhatsAppMessage::query()
            ->with('media')
            ->where('phone', $normalizedPhone)
            ->orderBy('sent_at')
            ->orderBy('created_at');

        if ($since) {
            $query->where('sent_at', '>', Carbon::parse($since));
        }

        $storedMessages = $query->limit($limit)->get();

        if ($storedMessages->isEmpty() && $since === null) {
            return $this->syncMessagesFromTwilio($phone, $limit);
        }

        return $storedMessages
            ->map(fn (WhatsAppMessage $message) => $this->formatStoredMessage($message))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeInboundMessage(array $payload): WhatsAppMessage
    {
        $from = (string) ($payload['from'] ?? '');
        $phone = $this->normalizePhoneDigits($from);
        $trainee = $this->findTraineeByPhone($phone);

        $message = WhatsAppMessage::query()->create([
            'twilio_sid' => $payload['twilio_sid'] ?? null,
            'trainee_id' => $trainee?->id,
            'phone' => $phone,
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'body' => $payload['body'] ?? '',
            'status' => $payload['status'] ?? 'received',
            'from_address' => $from,
            'to_address' => $payload['to'] ?? null,
            'sent_at' => $payload['sent_at'] ?? now(),
            'metadata' => $payload['metadata'] ?? null,
        ]);

        WhatsAppBroadcast::messageStored($message);

        return $message;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function storeOutboundMessage(array $message, string $phone, ?string $traineeId = null): WhatsAppMessage
    {
        $normalizedPhone = $this->normalizePhoneDigits($phone);
        $trainee = $traineeId
            ? Trainee::query()->find($traineeId)
            : $this->findTraineeByPhone($normalizedPhone);

        $attributes = [
            'trainee_id' => $trainee?->id,
            'phone' => $normalizedPhone,
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'body' => $message['body'] ?? '',
            'status' => $message['status'] ?? null,
            'from_address' => $message['from'] ?? null,
            'to_address' => $message['to'] ?? null,
            'sent_at' => isset($message['date_sent']) ? Carbon::parse($message['date_sent']) : now(),
        ];

        if (! empty($message['sid'])) {
            $stored = WhatsAppMessage::query()->updateOrCreate(
                ['twilio_sid' => $message['sid']],
                $attributes
            );
        } else {
            $stored = WhatsAppMessage::query()->create(array_merge($attributes, [
                'twilio_sid' => null,
            ]));
        }

        WhatsAppBroadcast::messageStored($stored);

        return $stored;
    }

    public function updateMessageStatus(string $twilioSid, string $status, ?string $errorMessage = null): void
    {
        $message = WhatsAppMessage::query()->where('twilio_sid', $twilioSid)->first();

        if (! $message) {
            return;
        }

        $metadata = $message->metadata ?? [];

        if ($errorMessage) {
            $metadata['error_message'] = $errorMessage;
        }

        $message->update([
            'status' => $status,
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }

    public function normalizePhoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', str_replace('whatsapp:', '', $phone)) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '05')) {
            $digits = '966' . substr($digits, 1);
        }

        if (str_starts_with($digits, '5') && strlen($digits) === 9) {
            $digits = '966' . $digits;
        }

        return $digits;
    }

    public function findTraineeByPhone(string $normalizedPhone): ?Trainee
    {
        $digits = preg_replace('/\D+/', '', $normalizedPhone) ?? '';
        $suffix = substr($digits, -9);

        if ($suffix === '' || strlen($suffix) < 9) {
            return null;
        }

        return Trainee::withTrashed()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function ($query) use ($normalizedPhone, $digits, $suffix) {
                $query->where('phone', 'LIKE', '%' . $normalizedPhone . '%')
                    ->orWhere('phone', 'LIKE', '%' . $digits . '%')
                    ->orWhere('phone', 'LIKE', '%' . $suffix);
            })
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function syncMessagesFromTwilio(string $phone, int $limit): array
    {
        $address = $this->toWhatsAppAddress($phone);
        $messages = [];

        foreach (['To' => $address, 'From' => $address] as $field => $value) {
            $response = $this->client()->get(
                self::MESSAGES_API . '/Accounts/' . config('twilio.account_sid') . '/Messages.json',
                [
                    'query' => [
                        $field => $value,
                        'PageSize' => $limit,
                    ],
                ]
            );

            $payload = json_decode((string) $response->getBody(), true);

            if ($response->getStatusCode() >= 400) {
                Log::warning('Twilio Messages API list failed', [
                    'status' => $response->getStatusCode(),
                    'field' => $field,
                    'response' => $payload,
                ]);

                continue;
            }

            foreach ($payload['messages'] ?? [] as $message) {
                $messages[$message['sid']] = $message;
            }
        }

        $formatted = [];

        foreach ($messages as $message) {
            $formattedMessage = $this->formatMessage($message);
            $isInbound = in_array($message['direction'] ?? '', ['inbound', 'inbound-api'], true);
            $counterpartyPhone = $isInbound
                ? $this->normalizePhoneDigits((string) ($message['from'] ?? ''))
                : $this->normalizePhoneDigits((string) ($message['to'] ?? ''));

            if ($counterpartyPhone !== $this->normalizePhoneDigits($phone)) {
                continue;
            }

            $stored = WhatsAppMessage::query()->updateOrCreate(
                ['twilio_sid' => $formattedMessage['sid']],
                [
                    'trainee_id' => $this->findTraineeByPhone($counterpartyPhone)?->id,
                    'phone' => $counterpartyPhone,
                    'direction' => $isInbound ? WhatsAppMessage::DIRECTION_INBOUND : WhatsAppMessage::DIRECTION_OUTBOUND,
                    'body' => $formattedMessage['body'],
                    'status' => $formattedMessage['status'],
                    'from_address' => $formattedMessage['from'],
                    'to_address' => $formattedMessage['to'],
                    'sent_at' => $formattedMessage['date_sent'] ? Carbon::parse($formattedMessage['date_sent']) : now(),
                ]
            );

            $formatted[] = $this->formatStoredMessage($stored);
        }

        usort($formatted, fn (array $a, array $b) => strcmp($a['date_sent'] ?? '', $b['date_sent'] ?? ''));

        return array_slice($formatted, -$limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStoredMessage(WhatsAppMessage $message): array
    {
        return $message->withPersistedMedia([
            'id' => $message->id,
            'sid' => $message->twilio_sid ?: $message->id,
            'phone' => $message->phone,
            'body' => $message->body ?? '',
            'status' => $message->status,
            'direction' => $message->direction === WhatsAppMessage::DIRECTION_OUTBOUND ? 'outbound-api' : 'inbound',
            'is_note' => (bool) $message->is_note,
            'from' => $message->from_address,
            'to' => $message->to_address,
            'date_sent' => optional($message->sent_at)->toIso8601String(),
            'error_message' => $message->metadata['error_message'] ?? null,
            'metadata' => is_array($message->metadata) ? $message->metadata : [],
        ]);
    }

    public function toE164(string $phone): string
    {
        return app(TwilioVerifyService::class)->toE164($phone);
    }

    public function toWhatsAppAddress(string $phone): string
    {
        $e164 = $this->toE164($phone);

        if (str_starts_with($e164, 'whatsapp:')) {
            return $e164;
        }

        return 'whatsapp:' . $e164;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function sendMessageRequest(array $params): array
    {
        if (filled(config('twilio.messaging_service_sid'))) {
            $params['MessagingServiceSid'] = config('twilio.messaging_service_sid');
        } else {
            $params['From'] = $this->toWhatsAppAddress(config('twilio.whatsapp_from'));
        }

        $statusCallback = config('twilio.status_callback_url');

        if (! filled($statusCallback)) {
            try {
                $statusCallback = route('webhooks.twilio.whatsapp.status');
            } catch (\Throwable) {
                $statusCallback = null;
            }
        }

        if (filled($statusCallback)) {
            $params['StatusCallback'] = $statusCallback;
        }

        $response = $this->client()->post(
            self::MESSAGES_API . '/Accounts/' . config('twilio.account_sid') . '/Messages.json',
            ['form_params' => $params]
        );

        $payload = json_decode((string) $response->getBody(), true);

        if ($response->getStatusCode() >= 400) {
            Log::error('Twilio WhatsApp send failed', [
                'status' => $response->getStatusCode(),
                'params' => array_merge($params, ['ContentVariables' => $params['ContentVariables'] ?? null]),
                'response' => $payload,
            ]);

            $message = $payload['message'] ?? 'Failed to send WhatsApp message.';

            throw new RuntimeException($message);
        }

        return $this->formatMessage($payload);
    }

    private function getApprovalStatus(string $contentSid): string
    {
        $response = $this->client()->get(self::CONTENT_API . '/Content/' . $contentSid . '/ApprovalRequests');

        $payload = json_decode((string) $response->getBody(), true);

        if ($response->getStatusCode() >= 400) {
            return 'unknown';
        }

        $whatsapp = $payload['whatsapp'] ?? [];

        return strtolower($whatsapp['status'] ?? 'unknown');
    }

    /**
     * @param  array<string, mixed>  $types
     */
    private function extractTemplateBody(array $types): string
    {
        foreach ($types as $typeConfig) {
            if (! is_array($typeConfig)) {
                continue;
            }

            if (! empty($typeConfig['body'])) {
                return (string) $typeConfig['body'];
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function extractVariableKeys(string $body): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        $keys = array_unique($matches[1] ?? []);
        sort($keys, SORT_NUMERIC);

        return array_values($keys);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function formatMessage(array $message): array
    {
        return [
            'sid' => $message['sid'] ?? null,
            'body' => $message['body'] ?? '',
            'status' => $message['status'] ?? null,
            'direction' => $message['direction'] ?? null,
            'from' => $message['from'] ?? null,
            'to' => $message['to'] ?? null,
            'date_sent' => $message['date_sent'] ?? $message['date_created'] ?? null,
            'error_message' => $message['error_message'] ?? null,
        ];
    }
}
