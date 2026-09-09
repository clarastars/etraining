<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Back\Trainee;
use App\Models\Back\WhatsAppConversation;
use Closure;

final class WhatsAppConversationTrainee
{
    /**
     * Eager-load constraint so soft-deleted (blocked/suspended) trainees still resolve.
     */
    public static function eagerLoadConstraint(): Closure
    {
        return static function ($query): void {
            $query->withTrashed()->select([
                'id',
                'name',
                'phone',
                'identity_number',
                'company_id',
                'suspended_at',
                'deleted_at',
                'deleted_remark',
            ]);
        };
    }

    /**
     * Force-reload trainee with trashed records included.
     *
     * @param  Closure|null  $companyConstraint  When set, also eager-loads trainee.company
     */
    public static function loadOnto(WhatsAppConversation $conversation, $companyConstraint = null): void
    {
        $conversation->unsetRelation('trainee');

        $with = [
            'trainee' => self::eagerLoadConstraint(),
        ];

        if ($companyConstraint !== null) {
            $with['trainee.company'] = $companyConstraint;
        }

        $conversation->load($with);
    }

    /**
     * Compact account status derived from the trainee row (no block-list lookup).
     *
     * @return array{is_active: bool, is_suspended: bool, is_blocked: bool, reason: ?string}
     */
    public static function accountStatus(Trainee $trainee): array
    {
        $isSuspended = $trainee->suspended_at !== null;
        $isBlocked = $trainee->trashed() && ! $isSuspended;
        $reason = ($isSuspended || $trainee->trashed())
            ? trim((string) ($trainee->deleted_remark ?? ''))
            : '';

        return [
            'is_active' => ! $isSuspended && ! $trainee->trashed(),
            'is_suspended' => $isSuspended,
            'is_blocked' => $isBlocked,
            'reason' => $reason !== '' ? $reason : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function format(?Trainee $trainee, ?string $companyName = null, ?string $companyShowUrl = null): ?array
    {
        if (! $trainee) {
            return null;
        }

        return [
            'id' => $trainee->id,
            'name' => $trainee->name,
            'phone' => $trainee->phone,
            'identity_number' => $trainee->identity_number,
            'company_name' => $companyName,
            'company_show_url' => $companyShowUrl,
            'show_url' => $trainee->show_url,
            'account_status' => self::accountStatus($trainee),
        ];
    }
}
