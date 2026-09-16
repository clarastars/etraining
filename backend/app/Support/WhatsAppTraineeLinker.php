<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Back\Trainee;
use App\Models\Back\WhatsAppConversation;
use App\Models\Back\WhatsAppMessage;
use App\Services\TelnyxWhatsAppService;

final class WhatsAppTraineeLinker
{
    /**
     * Attach a trainee to orphan WhatsApp conversations/messages that share their phone.
     */
    public static function linkOrphanRecordsForTrainee(Trainee $trainee, bool $broadcast = true): int
    {
        $phone = trim((string) ($trainee->phone ?? ''));
        if ($phone === '') {
            return 0;
        }

        $service = app(TelnyxWhatsAppService::class);
        $normalized = $service->normalizePhoneDigits($phone);
        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        $suffix = substr($digits, -9);

        if ($suffix === '' || strlen($suffix) < 9) {
            return 0;
        }

        $linked = 0;

        $conversations = WhatsAppConversation::query()
            ->whereNull('trainee_id')
            ->where(function ($query) use ($normalized, $digits, $suffix) {
                $query->where('phone', $normalized)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'LIKE', '%' . $suffix);
            })
            ->get();

        foreach ($conversations as $conversation) {
            $conversation->trainee_id = $trainee->id;
            $conversation->save();
            $conversation->setRelation('trainee', $trainee);
            $linked++;

            if ($broadcast) {
                WhatsAppConversationSync::broadcast($conversation);
            }
        }

        WhatsAppMessage::query()
            ->whereNull('trainee_id')
            ->where(function ($query) use ($normalized, $digits, $suffix) {
                $query->where('phone', $normalized)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'LIKE', '%' . $suffix);
            })
            ->update(['trainee_id' => $trainee->id]);

        return $linked;
    }

    /**
     * If a conversation has no trainee_id, resolve one by phone and persist it.
     */
    public static function attachTraineeIfMissing(WhatsAppConversation $conversation): WhatsAppConversation
    {
        if ($conversation->trainee_id) {
            WhatsAppConversationTrainee::loadOnto($conversation);

            return $conversation;
        }

        $phone = trim((string) ($conversation->phone ?? ''));
        if ($phone === '') {
            return $conversation;
        }

        $service = app(TelnyxWhatsAppService::class);
        $trainee = $service->findTraineeByPhone($service->normalizePhoneDigits($phone));

        if (! $trainee) {
            return $conversation;
        }

        $conversation->trainee_id = $trainee->id;
        $conversation->save();
        $conversation->setRelation('trainee', $trainee);

        return $conversation;
    }
}
