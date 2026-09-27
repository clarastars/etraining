<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Back\Trainee;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TraineeFixedTrainingCostsTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = (new CreateNewUser())->create([
            'name' => 'Shafiq al-Shaar',
            'email' => 'hello@getShafiq.com',
            'password' => 'hello123123',
            'password_confirmation' => 'hello123123',
        ]);
    }

    public function test_user_can_update_platform_reward(): void
    {
        $trainee = Trainee::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $this->actingAs($this->user)
            ->put(route('back.trainees.fixed-training-costs.update', $trainee->id), [
                'override_training_costs' => 2300,
                'platform_reward' => 1500,
                'ignore_attendance' => false,
                'dont_edit_notice' => false,
            ])
            ->assertRedirect($trainee->show_url);

        $this->assertDatabaseHas('trainees', [
            'id' => $trainee->id,
            'override_training_costs' => 2300,
            'platform_reward' => 1500,
        ]);
    }
}
