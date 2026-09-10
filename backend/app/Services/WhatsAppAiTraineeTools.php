<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Back\Invoice;
use App\Models\Back\Trainee;
use App\Support\WhatsAppConversationHandoff;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WhatsAppAiTraineeTools
{
    public function __construct(
        private readonly TelnyxWhatsAppService $whatsAppService,
        private readonly NoonService $noonService
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openAiToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_trainee_profile',
                    'description' => 'Look up the trainee linked to this WhatsApp conversation phone.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_contract_status',
                    'description' => 'Get Zoho contract / signing status for the trainee on this conversation.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_account_status',
                    'description' => 'Check whether the trainee account is suspended or blocked. When is_suspended or requires_human_handoff is true, a human agent handoff is required.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_pending_invoices',
                    'description' => 'List unpaid invoices and the most recently paid invoice. Call immediately when the trainee says she already paid (سددت / دفعت / تم السداد) or asks about invoice/payment status. Do not ask clarifying questions first.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_payment_link',
                    'description' => 'Create a Noon payment link for one unpaid invoice owned by this trainee. Do not call this when the trainee claims she already paid.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'invoice_id' => [
                                'type' => 'string',
                                'description' => 'Invoice UUID from get_pending_invoices',
                            ],
                        ],
                        'required' => ['invoice_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'request_human_agent',
                    'description' => 'Required before telling the trainee you transferred them. Call when the account is suspended/blocked, the question is out of scope, unclear, or handoff rules apply. Tags the chat need_human_agent and pauses the bot. Never claim a transfer without calling this tool.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'description' => 'Short reason for the handoff',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments, string $normalizedPhone): array
    {
        return match ($name) {
            'get_trainee_profile' => $this->getTraineeProfile($normalizedPhone),
            'get_contract_status' => $this->getContractStatus($normalizedPhone),
            'get_account_status' => $this->getAccountStatus($normalizedPhone),
            'get_pending_invoices' => $this->getPendingInvoices($normalizedPhone),
            'create_payment_link' => $this->createPaymentLink(
                $normalizedPhone,
                (string) ($arguments['invoice_id'] ?? '')
            ),
            'request_human_agent' => $this->requestHumanAgent(
                $normalizedPhone,
                isset($arguments['reason']) ? (string) $arguments['reason'] : null
            ),
            default => ['ok' => false, 'error' => 'unknown_tool'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getTraineeProfile(string $normalizedPhone): array
    {
        $trainee = $this->findTrainee($normalizedPhone);
        if (! $trainee) {
            return [
                'ok' => true,
                'found' => false,
                'message' => 'No trainee found for this WhatsApp number.',
            ];
        }

        $identity = (string) ($trainee->identity_number ?? '');
        $identityLast4 = $identity !== '' ? substr($identity, -4) : null;

        return [
            'ok' => true,
            'found' => true,
            'name' => (string) ($trainee->name ?? ''),
            'english_name' => (string) ($trainee->english_name ?? ''),
            'identity_last4' => $identityLast4,
            'company_name' => (string) ($trainee->company_name ?? ''),
            'phone' => (string) ($trainee->phone ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getContractStatus(string $normalizedPhone): array
    {
        $trainee = $this->findTrainee($normalizedPhone);
        if (! $trainee) {
            return ['ok' => true, 'found' => false];
        }

        $status = (string) ($trainee->zoho_contract_status ?? 'pending');
        $signed = $status === 'completed' && ! empty($trainee->zoho_sign_date);

        return [
            'ok' => true,
            'found' => true,
            'zoho_contract_status' => $status,
            'zoho_sign_date' => optional($trainee->zoho_sign_date)->toDateString(),
            'must_sign' => (bool) ($trainee->must_sign ?? false),
            'is_signed' => $signed,
            'contract_sent' => ! empty($trainee->zoho_contract_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccountStatus(string $normalizedPhone): array
    {
        $trainee = $this->findTrainee($normalizedPhone);
        if (! $trainee) {
            return ['ok' => true, 'found' => false];
        }

        $suspended = ! is_null($trainee->suspended_at) || $trainee->trashed();

        return [
            'ok' => true,
            'found' => true,
            'is_suspended' => $suspended,
            'requires_human_handoff' => $suspended,
            'suspended_at' => optional($trainee->suspended_at)->toIso8601String(),
            'deleted_at' => optional($trainee->deleted_at)->toIso8601String(),
            'reason' => $suspended ? (string) ($trainee->deleted_remark ?? '') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPendingInvoices(string $normalizedPhone): array
    {
        $trainee = $this->findTrainee($normalizedPhone);
        if (! $trainee) {
            return ['ok' => true, 'found' => false, 'invoices' => [], 'last_paid_invoice' => null];
        }

        $invoices = $trainee->invoices()
            ->notPaid()
            ->where('status', '!=', Invoice::STATUS_ARCHIVED)
            ->orderByDesc('from_date')
            ->get(['id', 'number', 'from_date', 'to_date', 'grand_total', 'status']);

        $lastPaid = $trainee->invoices()
            ->paid()
            ->where('status', '!=', Invoice::STATUS_ARCHIVED)
            ->orderByDesc('paid_at')
            ->first(['id', 'number', 'from_date', 'to_date', 'grand_total', 'status', 'paid_at']);

        return [
            'ok' => true,
            'found' => true,
            'count' => $invoices->count(),
            'invoices' => $invoices->map(static fn (Invoice $invoice) => [
                'id' => (string) $invoice->id,
                'number' => $invoice->number,
                'from_date' => optional($invoice->from_date)->toDateString(),
                'to_date' => optional($invoice->to_date)->toDateString(),
                'grand_total' => (float) $invoice->grand_total,
                'currency' => 'SAR',
            ])->values()->all(),
            'last_paid_invoice' => $lastPaid ? [
                'id' => (string) $lastPaid->id,
                'number' => $lastPaid->number,
                'from_date' => optional($lastPaid->from_date)->toDateString(),
                'to_date' => optional($lastPaid->to_date)->toDateString(),
                'grand_total' => (float) $lastPaid->grand_total,
                'paid_at' => optional($lastPaid->paid_at)->toDateString(),
                'currency' => 'SAR',
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createPaymentLink(string $normalizedPhone, string $invoiceId): array
    {
        $invoiceId = trim($invoiceId);
        if ($invoiceId === '') {
            return ['ok' => false, 'error' => 'invoice_id_required'];
        }

        $trainee = $this->findTrainee($normalizedPhone);
        if (! $trainee) {
            return ['ok' => false, 'error' => 'trainee_not_found'];
        }

        /** @var Invoice|null $invoice */
        $invoice = $trainee->invoices()
            ->notPaid()
            ->where('status', '!=', Invoice::STATUS_ARCHIVED)
            ->where('id', $invoiceId)
            ->first();

        if (! $invoice) {
            return ['ok' => false, 'error' => 'invoice_not_found_or_not_owned'];
        }

        try {
            $url = $this->noonService->createPaymentUrlForInvoice($invoice);

            return [
                'ok' => true,
                'invoice_id' => (string) $invoice->id,
                'payment_url' => $url,
                'grand_total' => (float) $invoice->grand_total,
                'currency' => 'SAR',
            ];
        } catch (Throwable $exception) {
            Log::error('WhatsApp AI: payment link failed', [
                'invoice_id' => $invoiceId,
                'error' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'payment_link_failed'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function requestHumanAgent(string $normalizedPhone, ?string $reason = null): array
    {
        return WhatsAppConversationHandoff::requestHumanAgent($normalizedPhone, $reason);
    }

    private function findTrainee(string $normalizedPhone): ?Trainee
    {
        $phone = $this->whatsAppService->normalizePhoneDigits($normalizedPhone);
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $suffix = substr($digits, -9);
        if ($suffix === '' || strlen($suffix) < 9) {
            return null;
        }

        // Include soft-deleted (suspended) trainees so account status can be reported.
        return Trainee::withTrashed()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function ($query) use ($phone, $digits, $suffix) {
                $query->where('phone', 'LIKE', '%' . $phone . '%')
                    ->orWhere('phone', 'LIKE', '%' . $digits . '%')
                    ->orWhere('phone', 'LIKE', '%' . $suffix);
            })
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('updated_at')
            ->first();
    }
}
