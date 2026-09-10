<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Back\Trainee;
use App\Models\Back\WhatsAppMessage;
use App\Models\Back\WhatsAppTemplateBinding;
use App\Support\WhatsAppBroadcast;
use App\Support\WhatsAppCsvPhoneParser;
use App\Support\WhatsAppTemplateTags;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelnyxWhatsAppService
{
    private const API_BASE = 'https://api.telnyx.com/v2';

    private ?Client $client = null;

    private function client(): Client
    {
        if ($this->client === null) {
            $this->client = new Client([
                'base_uri' => self::API_BASE . '/',
                'headers' => [
                    'Authorization' => 'Bearer ' . config('telnyx.api_key'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'http_errors' => false,
            ]);
        }

        return $this->client;
    }

    public function isConfigured(): bool
    {
        return filled(config('telnyx.api_key'))
            && filled(config('telnyx.whatsapp_from'));
    }

    public function canManageTemplates(): bool
    {
        return $this->isConfigured() && filled(config('telnyx.waba_id'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        $query = ['page[size]' => 100];

        if (filled(config('telnyx.waba_id'))) {
            $query['filter[waba_id]'] = config('telnyx.waba_id');
        }

        $payload = $this->request('GET', 'whatsapp/message_templates', [
            'query' => $query,
        ], 'Failed to fetch WhatsApp templates.');

        $templates = [];

        foreach ($payload['data'] ?? [] as $template) {
            $templates[] = $this->formatTemplate($template);
        }

        return $templates;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplate(string $templateId): array
    {
        $payload = $this->request(
            'GET',
            'whatsapp/message_templates/' . $templateId,
            [],
            'Failed to fetch WhatsApp template.'
        );

        return $this->formatTemplate($payload['data'] ?? $payload);
    }

    /**
     * Submit a new WhatsApp message template for Meta review.
     *
     * @param  array{
     *     name: string,
     *     category: string,
     *     language: string,
     *     body: string,
     *     header?: string|null,
     *     footer?: string|null,
     *     variable_samples?: array<string, string>
     * }  $input
     * @return array<string, mixed>
     */
    public function createTemplate(array $input): array
    {
        $this->assertCanManageTemplates();

        $normalized = WhatsAppTemplateTags::normalizeBody(
            (string) $input['body'],
            $input['variable_samples'] ?? [],
            (string) ($input['language'] ?? 'ar')
        );

        $input['body'] = $normalized['body'];
        $input['variable_samples'] = $normalized['samples'];

        $payload = $this->request('POST', 'whatsapp/message_templates', [
            'json' => [
                'waba_id' => config('telnyx.waba_id'),
                'name' => $input['name'],
                'category' => strtoupper($input['category']),
                'language' => $input['language'],
                'components' => $this->buildManageComponents($input),
            ],
        ], 'Failed to create WhatsApp template.');

        $template = $this->formatTemplate($payload['data'] ?? $payload);
        $this->saveTemplateBindings(
            (string) ($template['sid'] ?? ''),
            $normalized['bindings'],
            (string) ($input['name'] ?? ''),
            (string) ($input['language'] ?? '')
        );

        return $this->formatTemplate($payload['data'] ?? $payload);
    }

    /**
     * Update an existing template (APPROVED or REJECTED only). Re-submits for Meta review.
     *
     * @param  array{
     *     body: string,
     *     header?: string|null,
     *     footer?: string|null,
     *     variable_samples?: array<string, string>,
     *     buttons?: array<int, array<string, mixed>>,
     *     language?: string
     * }  $input
     * @return array<string, mixed>
     */
    public function updateTemplate(string $templateId, array $input): array
    {
        $this->assertCanManageTemplates();

        $existing = $this->getTemplate($templateId);
        $language = (string) ($input['language'] ?? $existing['language'] ?? 'ar');

        $normalized = WhatsAppTemplateTags::normalizeBody(
            (string) $input['body'],
            $input['variable_samples'] ?? [],
            $language
        );

        $input['body'] = $normalized['body'];
        $input['variable_samples'] = $normalized['samples'];

        $payload = $this->request('PATCH', 'whatsapp/message_templates/' . $templateId, [
            'json' => [
                'components' => $this->buildManageComponents($input),
            ],
        ], 'Failed to update WhatsApp template.');

        $this->saveTemplateBindings(
            $templateId,
            $normalized['bindings'],
            (string) ($existing['friendly_name'] ?? ''),
            $language
        );

        return $this->formatTemplate($payload['data'] ?? $payload);
    }

    /**
     * Permanently delete a WhatsApp message template.
     */
    public function deleteTemplate(string $templateId): void
    {
        $this->assertCanManageTemplates();

        $this->request(
            'DELETE',
            'whatsapp/message_templates/' . $templateId,
            [],
            'Failed to delete WhatsApp template.'
        );

        WhatsAppTemplateBinding::query()->where('template_sid', $templateId)->delete();
    }

    /**
     * @return array<int, array{tag: string, label: string, example: string, placeholder: string}>
     */
    public function availableAutoTags(string $language = 'ar'): array
    {
        return WhatsAppTemplateTags::availableForUi($language);
    }

    /**
     * @param  array<string, string>  $bindings
     */
    private function saveTemplateBindings(string $templateSid, array $bindings, string $name = '', string $language = ''): void
    {
        if ($templateSid === '') {
            return;
        }

        WhatsAppTemplateBinding::query()->updateOrCreate(
            ['template_sid' => $templateSid],
            [
                'template_name' => $name !== '' ? $name : null,
                'language' => $language !== '' ? $language : null,
                'bindings' => $bindings,
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    private function bindingsForTemplate(string $templateSid): array
    {
        if ($templateSid === '') {
            return [];
        }

        $record = WhatsAppTemplateBinding::query()->where('template_sid', $templateSid)->first();

        if (! $record || ! is_array($record->bindings)) {
            return [];
        }

        $bindings = [];
        foreach ($record->bindings as $key => $tag) {
            if (is_string($tag) && $tag !== '') {
                $bindings[(string) $key] = $tag;
            }
        }

        return $bindings;
    }

    private function assertCanManageTemplates(): void
    {
        if (! $this->canManageTemplates()) {
            throw new RuntimeException(
                'WhatsApp template management requires TELNYX_API_KEY, TELNYX_WHATSAPP_FROM, and TELNYX_WABA_ID.'
            );
        }
    }

    /**
     * @param  array{
     *     body: string,
     *     header?: string|null,
     *     footer?: string|null,
     *     variable_samples?: array<string, string>,
     *     buttons?: array<int, array<string, mixed>>
     * }  $input
     * @return array<int, array<string, mixed>>
     */
    private function buildManageComponents(array $input): array
    {
        $components = [];

        $header = trim((string) ($input['header'] ?? ''));
        if ($header !== '') {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $header,
            ];
        }

        $body = (string) $input['body'];
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $body,
        ];

        $samples = $this->resolveBodyExamples($body, $input['variable_samples'] ?? []);
        if ($samples !== []) {
            $bodyComponent['example'] = [
                'body_text' => [$samples],
            ];
        }

        $components[] = $bodyComponent;

        $footer = trim((string) ($input['footer'] ?? ''));
        if ($footer !== '') {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $footer,
            ];
        }

        $buttons = $this->normalizeButtons($input['buttons'] ?? []);
        if ($buttons !== []) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        }

        return $components;
    }

    /**
     * @param  array<int, mixed>  $buttons
     * @return array<int, array<string, mixed>>
     */
    private function normalizeButtons(array $buttons): array
    {
        $normalized = [];

        foreach (array_slice(array_values($buttons), 0, 3) as $button) {
            if (! is_array($button)) {
                continue;
            }

            $type = strtoupper(trim((string) ($button['type'] ?? '')));
            $text = trim((string) ($button['text'] ?? ''));

            if ($text === '' || ! in_array($type, ['QUICK_REPLY', 'URL', 'PHONE_NUMBER'], true)) {
                continue;
            }

            if ($type === 'QUICK_REPLY') {
                $normalized[] = [
                    'type' => 'QUICK_REPLY',
                    'text' => $text,
                ];
                continue;
            }

            if ($type === 'URL') {
                $url = trim((string) ($button['url'] ?? ''));
                if ($url === '') {
                    continue;
                }

                $item = [
                    'type' => 'URL',
                    'text' => $text,
                    'url' => $url,
                ];

                if (str_contains($url, '{{')) {
                    $example = trim((string) ($button['example'] ?? 'example'));
                    $item['example'] = [$example !== '' ? $example : 'example'];
                }

                $normalized[] = $item;
                continue;
            }

            $phone = trim((string) ($button['phone_number'] ?? ''));
            if ($phone === '') {
                continue;
            }

            $normalized[] = [
                'type' => 'PHONE_NUMBER',
                'text' => $text,
                'phone_number' => $phone,
            ];
        }

        if ($normalized === []) {
            return [];
        }

        $types = array_unique(array_map(
            static fn (array $button): string => (string) $button['type'],
            $normalized
        ));
        $hasQuickReply = in_array('QUICK_REPLY', $types, true);
        $hasCallToAction = in_array('URL', $types, true) || in_array('PHONE_NUMBER', $types, true);

        if ($hasQuickReply && $hasCallToAction) {
            throw new RuntimeException(
                'WhatsApp templates cannot mix QUICK_REPLY buttons with URL or PHONE_NUMBER buttons.'
            );
        }

        if ($hasQuickReply && count($normalized) > 3) {
            throw new RuntimeException('A template can have at most 3 QUICK_REPLY buttons.');
        }

        if ($hasCallToAction && count($normalized) > 2) {
            throw new RuntimeException('A template can have at most 2 call-to-action buttons (URL / phone).');
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $variableSamples
     * @return array<int, string>
     */
    private function resolveBodyExamples(string $body, array $variableSamples): array
    {
        $keys = $this->extractVariableKeys($body);

        if ($keys === []) {
            return [];
        }

        $examples = [];

        foreach ($keys as $key) {
            $sample = trim((string) ($variableSamples[$key] ?? $variableSamples[(int) $key] ?? ''));
            $examples[] = $sample !== '' ? $sample : 'example' . $key;
        }

        return $examples;
    }

    /**
     * @param  array<string, string>  $contentVariables
     * @return array<string, mixed>
     */
    public function sendTemplate(string $phone, string $templateId, array $contentVariables = [], ?string $traineeId = null): array
    {
        $template = $this->getTemplate($templateId);
        $trainee = $traineeId
            ? Trainee::query()->with('company:id,name_ar')->find($traineeId)
            : $this->findTraineeByPhone($this->normalizePhoneDigits($phone));

        if ($trainee && ! $trainee->relationLoaded('company')) {
            $trainee->load('company:id,name_ar');
        }

        $contentVariables = $this->resolveTemplateVariables($template, $contentVariables, $trainee);
        $components = $this->buildTemplateComponents($contentVariables);

        $templatePayload = ['components' => $components];

        if (! empty($template['friendly_name']) && ! empty($template['language'])) {
            $templatePayload['name'] = $template['friendly_name'];
            $templatePayload['language'] = [
                'policy' => 'deterministic',
                'code' => $template['language'],
            ];
        } elseif ($templateId !== '') {
            $templatePayload['template_id'] = $templateId;
        }

        $message = $this->sendWhatsAppMessage($phone, [
            'type' => 'template',
            'template' => $templatePayload,
        ], $this->previewTemplateBody($template, $contentVariables));
        $this->storeOutboundMessage($message, $phone, $trainee?->id ?? $traineeId);

        return $message;
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $contentVariables
     * @return array<string, string>
     */
    private function resolveTemplateVariables(array $template, array $contentVariables, ?Trainee $trainee): array
    {
        $resolved = [];
        foreach ($contentVariables as $key => $value) {
            $resolved[(string) $key] = (string) $value;
        }

        $bindings = is_array($template['variable_bindings'] ?? null) ? $template['variable_bindings'] : [];

        foreach ($template['variables'] ?? [] as $key) {
            $key = (string) $key;
            $tag = (string) ($bindings[$key] ?? '');

            if ($tag === '' || ! WhatsAppTemplateTags::isAutoTag($tag)) {
                continue;
            }

            $autoValue = WhatsAppTemplateTags::resolve($tag, $trainee);
            if ($autoValue !== null && $autoValue !== '') {
                $resolved[$key] = $autoValue;
            }
        }

        foreach ($template['variables'] ?? [] as $key) {
            $key = (string) $key;
            if (! array_key_exists($key, $resolved) || trim((string) $resolved[$key]) === '') {
                $tag = (string) ($bindings[$key] ?? $key);
                throw new RuntimeException(
                    'Missing value for template variable ' . $tag . '.'
                );
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function sendFreeformMessage(string $phone, string $body, ?string $traineeId = null): array
    {
        $message = $this->sendWhatsAppMessage($phone, [
            'type' => 'text',
            'text' => [
                'body' => $body,
                'preview_url' => false,
            ],
        ], $body);

        $this->storeOutboundMessage($message, $phone, $traineeId);

        return $message;
    }

    /**
     * Send a bot auto-reply and store it with metadata.is_bot so agent UIs show it as a bot message.
     *
     * @return array<string, mixed>
     */
    public function sendBotFreeformMessage(string $phone, string $body, ?string $traineeId = null): array
    {
        $message = $this->sendWhatsAppMessage($phone, [
            'type' => 'text',
            'text' => [
                'body' => $body,
                'preview_url' => false,
            ],
        ], $body);

        $this->storeOutboundMessage($message, $phone, $traineeId, [
            'is_bot' => true,
        ]);

        return $message;
    }

    /**
     * Persist an outbound bot message without calling the Telnyx API (dev / fallback).
     */
    public function storeLocalBotMessage(string $phone, string $body, ?string $traineeId = null): WhatsAppMessage
    {
        $normalized = $this->normalizePhoneDigits($phone);

        $message = WhatsAppMessage::query()->create([
            'trainee_id' => $traineeId,
            'phone' => $normalized,
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'body' => $body,
            'status' => 'sent',
            'from_address' => $this->normalizePhoneDigits((string) config('telnyx.whatsapp_from')),
            'to_address' => $normalized,
            'sent_at' => now(),
            'metadata' => ['is_bot' => true],
        ]);

        WhatsAppBroadcast::messageStored($message);

        return $message;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessages(string $phone, int $limit = 30, ?string $since = null): array
    {
        $normalizedPhone = $this->normalizePhoneDigits($phone);
        $phoneDigits = preg_replace('/\D+/', '', $normalizedPhone) ?? '';
        $suffix = substr($phoneDigits, -9);

        $query = WhatsAppMessage::query()
            ->with('media')
            ->where(function ($builder) use ($normalizedPhone, $phoneDigits, $suffix) {
                $builder->where('phone', $normalizedPhone);

                if ($phoneDigits !== '' && ('+' . $phoneDigits) !== $normalizedPhone) {
                    $builder->orWhere('phone', $phoneDigits)
                        ->orWhere('phone', '+' . $phoneDigits);
                }

                if (strlen($suffix) === 9) {
                    $builder->orWhere('phone', 'LIKE', '%' . $suffix);
                }
            })
            ->orderBy('sent_at')
            ->orderBy('created_at');

        if ($since) {
            $query->where('sent_at', '>', Carbon::parse($since));
        }

        return $query->limit($limit)
            ->get()
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

        $message = WhatsAppMessage::query()->create([
            'twilio_sid' => $payload['external_id'] ?? null,
            'trainee_id' => $this->findTraineeByPhone($phone)?->id,
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
     * @param  array<string, mixed>  $metadata
     */
    public function storeOutboundMessage(
        array $message,
        string $phone,
        ?string $traineeId = null,
        array $metadata = []
    ): WhatsAppMessage {
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
            'metadata' => $metadata !== [] ? $metadata : null,
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

    public function updateMessageStatus(string $externalId, string $status, ?string $errorMessage = null): void
    {
        $message = WhatsAppMessage::query()->where('twilio_sid', $externalId)->first();

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
        $phone = WhatsAppCsvPhoneParser::toAsciiDigits(str_replace('whatsapp:', '', $phone));
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '05')) {
            $digits = '966' . substr($digits, 1);
        }

        if (str_starts_with($digits, '5') && strlen($digits) === 9) {
            $digits = '966' . $digits;
        }

        // Ensure the number starts with a plus sign and the country code
        if (!str_starts_with($digits, '+')) {
            $digits = '+' . $digits;
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

        // Prefer an active trainee; fall back to soft-deleted (blocked/suspended) profiles
        // so chat can still resolve +966… conversations to trainees stored as 05….
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

    // Removed toE164 as Telnyx seems to handle it internally if the number is properly formatted.
    // If issues persist, consider re-introducing or using a dedicated E.164 formatting library.

    /**
     * @param  array<string, mixed>  $whatsappMessage
     * @return array<string, mixed>
     */
    private function sendWhatsAppMessage(string $phone, array $whatsappMessage, string $bodyForStorage = ''): array
    {
        // Ensure the phone number is normalized before sending
        $to = $this->normalizePhoneDigits($phone);
        $from = $this->normalizePhoneDigits(config('telnyx.whatsapp_from'));


        $params = [
            'from' => $from,
            'to' => $to,
            'whatsapp_message' => $whatsappMessage,
        ];

        $messagingProfileId = config('telnyx.messaging_profile_id');
        if (filled($messagingProfileId)) {
            $params['messaging_profile_id'] = $messagingProfileId;
        }

        $webhookUrl = config('telnyx.status_callback_url');

        if (! filled($webhookUrl)) {
            try {
                $webhookUrl = route('webhooks.telnyx.whatsapp');
            } catch (\Throwable) {
                $webhookUrl = null;
            }
        }

        if (filled($webhookUrl)) {
            $params['webhook_url'] = $webhookUrl;
        }

        $payload = $this->request('POST', 'messages/whatsapp', [
            'json' => $params,
        ], 'Failed to send WhatsApp message.');

        $data = $payload['data'] ?? [];

        return [
            'sid' => $data['id'] ?? null,
            'body' => $bodyForStorage,
            'status' => $data['to'][0]['status'] ?? $data['status'] ?? 'queued',
            'direction' => 'outbound-api',
            'from' => $data['from']['phone_number'] ?? $params['from'],
            'to' => $data['to'][0]['phone_number'] ?? $params['to'],
            'date_sent' => now()->toIso8601String(),
            'error_message' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $options = [], string $errorMessage = 'Telnyx API request failed.'): array
    {
        $response = $this->client()->request($method, $uri, $options);
        $payload = json_decode((string) $response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 400) {
            $context = [
                'method' => $method,
                'uri' => $uri,
                'status' => $response->getStatusCode(),
                'response' => $payload,
            ];

            $fromNumber = $options['json']['from'] ?? null;
            $toNumber = $options['json']['to'] ?? null;

            if ($fromNumber) {
                $context['from_number'] = $fromNumber;
            }
            if ($toNumber) {
                $context['to_number'] = $toNumber;
            }

            Log::error('Telnyx WhatsApp API request failed', $context);

            $errorDetails = $payload['errors'][0]['detail']
                ?? $payload['errors'][0]['title']
                ?? json_encode($payload['errors'] ?? $payload); // Fallback to full errors or payload

            $message = sprintf(
                "Telnyx API request failed with status %d: %s",
                $response->getStatusCode(),
                (string) $errorDetails
            );

            if ($fromNumber || $toNumber) {
                $configuredFrom = config('telnyx.whatsapp_from');
                $apiKeyPrefix = substr((string) config('telnyx.api_key'), 0, 5) . '...';
                $message .= " (Configured From: {$configuredFrom})";
                $message .= " (Normalized From: {$fromNumber})";
                $message .= " (To: {$toNumber})";
                $message .= " (API Key: {$apiKeyPrefix})";
            }

            throw new RuntimeException($message);
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function formatTemplate(array $template): array
    {
        $components = is_array($template['components'] ?? null) ? $template['components'] : [];
        $body = $this->extractTemplateBody($components);
        $variables = $this->extractVariableKeys($body);
        $variableSamples = $this->extractVariableSamples($components);
        $bindings = $this->bindingsForTemplate((string) ($template['id'] ?? ''));

        // Legacy fallback: single {{1}} with no bindings was used as trainee name in Finance chat.
        if ($bindings === [] && $variables === ['1']) {
            $bindings = ['1' => 'trainee_name'];
        }

        if ($variables === [] && $variableSamples !== []) {
            $variables = array_map('strval', array_keys($variableSamples));
            sort($variables, SORT_NUMERIC);
        }

        $autoVariables = [];
        $manualVariables = [];

        foreach ($variables as $key) {
            $key = (string) $key;
            $tag = (string) ($bindings[$key] ?? '');

            if ($tag !== '' && WhatsAppTemplateTags::isAutoTag($tag)) {
                $autoVariables[$key] = $tag;
            } else {
                $manualVariables[] = $key;
            }
        }

        return [
            'sid' => $template['id'] ?? '',
            'friendly_name' => $template['name'] ?? '',
            'language' => $template['language'] ?? '',
            'body' => $body,
            'body_display' => WhatsAppTemplateTags::applyBindingsToBody($body, $bindings),
            'header' => $this->extractTemplateHeader($components),
            'footer' => $this->extractTemplateFooter($components),
            'buttons' => $this->extractTemplateButtons($components),
            'components' => $components,
            'variables' => array_values($variables),
            'manual_variables' => array_values($manualVariables),
            'auto_variables' => $autoVariables,
            'variable_bindings' => $bindings,
            'variable_samples' => $variableSamples,
            'approval_status' => strtolower((string) ($template['status'] ?? 'unknown')),
            'category' => $template['category'] ?? null,
            'quality_rating' => $template['quality_rating'] ?? null,
            'can_edit' => in_array(strtoupper((string) ($template['status'] ?? '')), ['APPROVED', 'REJECTED'], true),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function extractTemplateHeader(array $components): string
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            if (strtoupper((string) ($component['type'] ?? '')) === 'HEADER'
                && strtoupper((string) ($component['format'] ?? 'TEXT')) === 'TEXT'
                && ! empty($component['text'])
            ) {
                return (string) $component['text'];
            }
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function extractTemplateFooter(array $components): string
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            if (strtoupper((string) ($component['type'] ?? '')) === 'FOOTER' && ! empty($component['text'])) {
                return (string) $component['text'];
            }
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, array<string, mixed>>
     */
    private function extractTemplateButtons(array $components): array
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            if (strtoupper((string) ($component['type'] ?? '')) !== 'BUTTONS') {
                continue;
            }

            $buttons = [];
            foreach ($component['buttons'] ?? [] as $button) {
                if (! is_array($button)) {
                    continue;
                }

                $type = strtoupper((string) ($button['type'] ?? ''));
                $text = (string) ($button['text'] ?? '');

                if ($text === '' || $type === '') {
                    continue;
                }

                $item = [
                    'type' => $type,
                    'text' => $text,
                ];

                if ($type === 'URL') {
                    $item['url'] = (string) ($button['url'] ?? '');
                    $example = $button['example'][0] ?? $button['example'] ?? null;
                    $item['example'] = is_string($example) ? $example : '';
                }

                if ($type === 'PHONE_NUMBER') {
                    $item['phone_number'] = (string) ($button['phone_number'] ?? '');
                }

                $buttons[] = $item;
            }

            return $buttons;
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function extractTemplateBody(array $components): string
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            if (strtoupper((string) ($component['type'] ?? '')) === 'BODY' && ! empty($component['text'])) {
                return (string) $component['text'];
            }
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, string>
     */
    private function extractVariableSamples(array $components): array
    {
        $samples = [];

        foreach ($components as $component) {
            if (! is_array($component) || strtoupper((string) ($component['type'] ?? '')) !== 'BODY') {
                continue;
            }

            $bodyText = $component['example']['body_text'][0] ?? null;

            if (! is_array($bodyText)) {
                continue;
            }

            foreach (array_values($bodyText) as $index => $value) {
                $samples[(string) ($index + 1)] = (string) $value;
            }
        }

        return $samples;
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
     * @param  array<string, string>  $contentVariables
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplateComponents(array $contentVariables): array
    {
        if ($contentVariables === []) {
            return [];
        }

        ksort($contentVariables, SORT_NUMERIC);

        $parameters = [];

        foreach ($contentVariables as $value) {
            $parameters[] = [
                'type' => 'text',
                'text' => (string) $value,
            ];
        }

        return [
            [
                'type' => 'body',
                'parameters' => $parameters,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $contentVariables
     */
    private function previewTemplateBody(array $template, array $contentVariables): string
    {
        $body = (string) ($template['body'] ?? '');

        foreach ($contentVariables as $key => $value) {
            $body = str_replace('{{' . $key . '}}', (string) $value, $body);
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatStoredMessage(WhatsAppMessage $message): array
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $isBot = ! empty($metadata['is_bot']);

        return $message->withPersistedMedia([
            'id' => $message->id,
            'sid' => $message->twilio_sid ?: $message->id,
            'phone' => $message->phone,
            'body' => $message->body ?? '',
            'status' => $message->status,
            'direction' => $message->direction === WhatsAppMessage::DIRECTION_OUTBOUND ? 'outbound-api' : 'inbound',
            'is_note' => (bool) $message->is_note,
            'is_bot' => $isBot,
            'from' => $message->from_address,
            'to' => $message->to_address,
            'date_sent' => optional($message->sent_at)->toIso8601String(),
            'error_message' => $metadata['error_message'] ?? null,
            'metadata' => $metadata,
        ]);
    }
}
