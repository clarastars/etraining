<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Back\Trainee;
use App\Support\WhatsAppConversationTrainee;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Tests\CreatesApplication;

class WhatsAppConversationTraineeTest extends BaseTestCase
{
    use CreatesApplication;

    public function test_account_status_for_active_trainee(): void
    {
        $trainee = new Trainee();
        $trainee->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'Active',
            'suspended_at' => null,
            'deleted_at' => null,
            'deleted_remark' => null,
        ]);

        $status = WhatsAppConversationTrainee::accountStatus($trainee);

        $this->assertTrue($status['is_active']);
        $this->assertFalse($status['is_suspended']);
        $this->assertFalse($status['is_blocked']);
        $this->assertNull($status['reason']);
    }

    public function test_account_status_for_suspended_trainee(): void
    {
        $trainee = new Trainee();
        $trainee->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'Suspended',
            'suspended_at' => now(),
            'deleted_at' => now(),
            'deleted_remark' => 'غير سداد',
        ]);

        $status = WhatsAppConversationTrainee::accountStatus($trainee);

        $this->assertFalse($status['is_active']);
        $this->assertTrue($status['is_suspended']);
        $this->assertFalse($status['is_blocked']);
        $this->assertSame('غير سداد', $status['reason']);
    }

    public function test_account_status_for_blocked_trainee(): void
    {
        $trainee = new Trainee();
        $trainee->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'Blocked',
            'suspended_at' => null,
            'deleted_at' => now(),
            'deleted_remark' => 'طلب حظر',
        ]);

        $status = WhatsAppConversationTrainee::accountStatus($trainee);

        $this->assertFalse($status['is_active']);
        $this->assertFalse($status['is_suspended']);
        $this->assertTrue($status['is_blocked']);
        $this->assertSame('طلب حظر', $status['reason']);
    }

    public function test_format_includes_account_status_and_blocked_show_url(): void
    {
        $trainee = new Trainee();
        $trainee->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'Blocked',
            'phone' => '0512345678',
            'identity_number' => '123',
            'company_id' => null,
            'suspended_at' => null,
            'deleted_at' => now(),
            'deleted_remark' => 'طلب حظر',
        ]);

        $formatted = WhatsAppConversationTrainee::format($trainee, 'Acme', null);

        $this->assertSame('Blocked', $formatted['name']);
        $this->assertSame('Acme', $formatted['company_name']);
        $this->assertTrue($formatted['account_status']['is_blocked']);
        $this->assertStringContainsString('blocked', $formatted['show_url']);
    }
}
