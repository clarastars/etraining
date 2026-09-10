<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Back\Trainee;
use App\Models\Back\WhatsAppConversation;
use App\Models\Back\WhatsAppMessage;
use App\Support\WhatsAppTraineeLinker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\CreatesApplication;

class WhatsAppTraineeLinkerTest extends BaseTestCase
{
    use CreatesApplication;

    private string $conversationPhone = '+966512345678';

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        config(['telnyx.whatsapp_from' => '+966500000001']);

        $this->createMinimalSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('trainees');

        parent::tearDown();
    }

    public function test_links_orphan_conversation_when_trainee_is_created_later(): void
    {
        $conversation = WhatsAppConversation::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => $this->conversationPhone,
            'trainee_id' => null,
            'status' => WhatsAppConversation::STATUS_OPEN,
        ]);

        WhatsAppMessage::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => $this->conversationPhone,
            'trainee_id' => null,
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'body' => 'hello',
            'status' => 'received',
            'sent_at' => now(),
        ]);

        $trainee = $this->makeTrainee('0512345678');

        $linked = WhatsAppTraineeLinker::linkOrphanRecordsForTrainee($trainee, false);

        $this->assertSame(1, $linked);
        $this->assertSame($trainee->id, $conversation->fresh()->trainee_id);
        $this->assertSame(
            $trainee->id,
            WhatsAppMessage::query()->where('phone', $this->conversationPhone)->value('trainee_id')
        );
    }

    public function test_attach_trainee_if_missing_resolves_by_phone(): void
    {
        $trainee = $this->makeTrainee('0512345678');

        $conversation = WhatsAppConversation::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => $this->conversationPhone,
            'trainee_id' => null,
            'status' => WhatsAppConversation::STATUS_OPEN,
        ]);

        WhatsAppTraineeLinker::attachTraineeIfMissing($conversation);

        $this->assertSame($trainee->id, $conversation->fresh()->trainee_id);
        $this->assertTrue($conversation->relationLoaded('trainee'));
        $this->assertSame($trainee->id, $conversation->trainee->id);
    }

    public function test_attach_trainee_if_missing_loads_soft_deleted_trainee(): void
    {
        $trainee = $this->makeTrainee('0512345678');
        $trainee->deleted_remark = 'non-payment';
        $trainee->suspended_at = now();
        $trainee->save();
        $trainee->delete();

        $conversation = WhatsAppConversation::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => $this->conversationPhone,
            'trainee_id' => $trainee->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
        ]);

        WhatsAppTraineeLinker::attachTraineeIfMissing($conversation);

        $this->assertTrue($conversation->relationLoaded('trainee'));
        $this->assertNotNull($conversation->trainee);
        $this->assertSame($trainee->id, $conversation->trainee->id);
        $this->assertTrue($conversation->trainee->trashed());
        $this->assertSame('non-payment', $conversation->trainee->deleted_remark);
    }

    public function test_attach_trainee_if_missing_links_soft_deleted_trainee_by_local_phone_format(): void
    {
        $trainee = $this->makeTrainee('0533898058');
        $trainee->deleted_remark = 'استبعاد';
        $trainee->suspended_at = now();
        $trainee->save();
        $trainee->delete();

        $conversation = WhatsAppConversation::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => '+966533898058',
            'trainee_id' => null,
            'status' => WhatsAppConversation::STATUS_OPEN,
        ]);

        WhatsAppTraineeLinker::attachTraineeIfMissing($conversation);

        $this->assertSame($trainee->id, $conversation->fresh()->trainee_id);
        $this->assertTrue($conversation->relationLoaded('trainee'));
        $this->assertNotNull($conversation->trainee);
        $this->assertTrue($conversation->trainee->trashed());
        $this->assertSame('استبعاد', $conversation->trainee->deleted_remark);
    }

    public function test_attach_trainee_if_missing_prefers_active_trainee_over_soft_deleted(): void
    {
        $deleted = $this->makeTrainee('0533898058');
        $deleted->delete();

        $active = $this->makeTrainee('0533898058');

        $conversation = WhatsAppConversation::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => '+966533898058',
            'trainee_id' => null,
            'status' => WhatsAppConversation::STATUS_OPEN,
        ]);

        WhatsAppTraineeLinker::attachTraineeIfMissing($conversation);

        $this->assertSame($active->id, $conversation->fresh()->trainee_id);
        $this->assertFalse($conversation->trainee->trashed());
    }

    public function test_attach_trainee_if_missing_links_via_phone_additional(): void
    {
        $trainee = $this->makeTrainee('0599999999');
        $trainee->phone_additional = '0533898058';
        $trainee->save();

        $conversation = WhatsAppConversation::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => '+966533898058',
            'trainee_id' => null,
            'status' => WhatsAppConversation::STATUS_OPEN,
        ]);

        WhatsAppTraineeLinker::attachTraineeIfMissing($conversation);

        $this->assertSame($trainee->id, $conversation->fresh()->trainee_id);
    }

    public function test_does_not_steal_conversation_already_linked_to_another_trainee(): void
    {
        $existingTrainee = $this->makeTrainee('0599999999');
        $newTrainee = $this->makeTrainee('0512345678');

        $conversation = WhatsAppConversation::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => $this->conversationPhone,
            'trainee_id' => $existingTrainee->id,
            'status' => WhatsAppConversation::STATUS_OPEN,
        ]);

        $linked = WhatsAppTraineeLinker::linkOrphanRecordsForTrainee($newTrainee, false);

        $this->assertSame(0, $linked);
        $this->assertSame($existingTrainee->id, $conversation->fresh()->trainee_id);
    }

    private function makeTrainee(string $phone): Trainee
    {
        return Trainee::withoutEvents(function () use ($phone) {
            $trainee = new Trainee();
            $trainee->forceFill([
                'id' => (string) Str::uuid(),
                'name' => 'Test Trainee',
                'phone' => $phone,
            ]);
            $trainee->save();

            return $trainee;
        });
    }

    private function createMinimalSchema(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('trainees');

        Schema::create('trainees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_additional')->nullable();
            $table->string('identity_number')->nullable();
            $table->uuid('team_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('deleted_remark')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 30)->unique();
            $table->uuid('trainee_id')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('bot_paused_until')->nullable();
            $table->text('last_message_body')->nullable();
            $table->string('last_message_direction', 20)->nullable();
            $table->boolean('last_message_is_note')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 30)->nullable();
            $table->uuid('trainee_id')->nullable();
            $table->string('direction', 32)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 32)->nullable();
            $table->boolean('is_note')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }
}
