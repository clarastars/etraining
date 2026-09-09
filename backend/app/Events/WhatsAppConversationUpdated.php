<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Back\WhatsAppConversation;
use App\Models\Back\Invoice;
use App\Support\WhatsAppBotPause;
use App\Support\WhatsAppConversationTrainee;
use App\Support\WhatsAppMessagingWindow;
use App\Support\WhatsAppTraineeLinker;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public array $conversation;

    /**
     * @param  array<string, mixed>  $conversation
     */
    public function __construct(array $conversation)
    {
        $this->conversation = $conversation;
    }

    public static function fromModel(WhatsAppConversation $conversation): self
    {
        WhatsAppTraineeLinker::attachTraineeIfMissing($conversation);

        $conversation->loadMissing([
            'agents:id,name',
            'tags:id,name,color',
        ]);
        WhatsAppConversationTrainee::loadOnto(
            $conversation,
            static function ($query): void {
                $query->select(['id', 'name_ar']);
            }
        );

        $authId = auth()->id();
        $trainee = $conversation->trainee;

        return new self([
            'id' => $conversation->id,
            'phone' => $conversation->phone,
            'status' => $conversation->status ?: WhatsAppConversation::STATUS_OPEN,
            'trainee' => WhatsAppConversationTrainee::format(
                $trainee,
                $trainee?->company?->name_ar,
                $trainee && $trainee->company_id
                    ? route('back.companies.show', $trainee->company_id)
                    : null,
            ),
            'last_message' => [
                'body' => $conversation->last_message_body,
                'direction' => $conversation->last_message_direction,
                'is_note' => (bool) $conversation->last_message_is_note,
                'sent_at' => optional($conversation->last_message_at)->toIso8601String(),
            ],
            'messaging_window' => WhatsAppMessagingWindow::forConversation($conversation),
            'updated_at' => optional($conversation->last_message_at)->timestamp
                ?? optional($conversation->updated_at)->timestamp
                ?? 0,
            'agents' => $conversation->agents->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])->values()->all(),
            'tags' => $conversation->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values()->all(),
            'is_assigned_to_me' => $authId
                ? $conversation->agents->contains('id', $authId)
                : false,
            'is_unassigned' => $conversation->agents->isEmpty(),
            'has_unread' => (bool) $conversation->has_unread,
            'bot_is_paused' => WhatsAppBotPause::isPaused($conversation),
            'bot_paused_until' => optional($conversation->bot_paused_until)->toIso8601String(),
            'unpaid_invoice_count' => Invoice::unpaidCountForTrainee(
                $conversation->trainee_id ? (string) $conversation->trainee_id : null
            ),
        ]);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('whatsapp-chat');
    }

    public function broadcastAs(): string
    {
        return 'WhatsAppConversationUpdated';
    }
}
