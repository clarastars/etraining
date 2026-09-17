<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Back\Company;
use App\Models\Back\TrainingDisclosureRequest;
use App\Models\User;
use App\Services\RolesService;
use Tests\TestCase;

class TrainingDisclosureRequestsTest extends TestCase
{
    private function makeAdminWithTeam(): User
    {
        $admin = User::factory()->create();
        $team = $admin->ownedTeams()->create([
            'name' => 'Test Team Disclosure Requests',
            'personal_team' => false,
        ]);
        app(RolesService::class)->seedRolesToTeam($team);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        return $admin->fresh();
    }

    public function test_can_create_disclosure_request_with_e_number_sequence(): void
    {
        $admin = $this->makeAdminWithTeam();
        $this->actingAs($admin);

        $company = Company::factory()->create([
            'team_id' => $admin->current_team_id,
            'name_ar' => 'شركة الاختبار',
            'name_en' => 'Test Co',
            'shelf_number' => 'SH-DISC-1',
        ]);

        $this->post(route('back.training-disclosure.requests.store'), [
            'company_id' => $company->id,
            'company_name' => 'شركة الاختبار',
            'trainees_count' => 2,
            'trainees' => [
                ['name' => 'أحمد', 'phone' => '0500000001', 'email' => 'a@example.com'],
                ['name' => 'سارة', 'phone' => '0500000002', 'email' => 's@example.com'],
            ],
        ])
            ->assertRedirect();

        $first = TrainingDisclosureRequest::query()->first();
        $this->assertNotNull($first);
        $this->assertSame('E100', $first->number);
        $this->assertSame(2, $first->trainees_count);
        $this->assertCount(2, $first->trainees_draft);
        $this->assertSame('أحمد', $first->trainees_draft[0]['name']);

        $this->post(route('back.training-disclosure.requests.store'), [
            'company_id' => $company->id,
            'company_name' => 'شركة الاختبار',
            'trainees_count' => 1,
            'trainees' => [
                ['name' => 'خالد', 'phone' => null, 'email' => null],
            ],
        ])
            ->assertRedirect();

        $second = TrainingDisclosureRequest::query()->where('number', 'E101')->first();
        $this->assertNotNull($second);
    }

    public function test_hq_index_includes_recent_disclosure_requests(): void
    {
        $admin = $this->makeAdminWithTeam();

        TrainingDisclosureRequest::query()->create([
            'team_id' => $admin->current_team_id,
            'number' => 'E100',
            'company_name' => 'Acme',
            'trainees_count' => 3,
            'trainees_draft' => [
                ['name' => 'A', 'phone' => null, 'email' => null],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('back.training-disclosure.index'))
            ->assertOk()
            ->assertPropValue('stats', function ($stats) {
                $this->assertSame(1, $stats['disclosure_requests']);
            })
            ->assertPropCount('recentDisclosureRequests', 1);
    }

    public function test_can_update_and_delete_disclosure_request(): void
    {
        $admin = $this->makeAdminWithTeam();
        $request = TrainingDisclosureRequest::query()->create([
            'team_id' => $admin->current_team_id,
            'number' => 'E100',
            'company_name' => 'Old Co',
            'trainees_count' => 1,
            'trainees_draft' => [
                ['name' => 'Old', 'phone' => null, 'email' => null],
            ],
        ]);

        $this->actingAs($admin)
            ->put(route('back.training-disclosure.requests.update', $request), [
                'company_id' => null,
                'company_name' => 'New Co',
                'trainees_count' => 2,
                'trainees' => [
                    ['name' => 'One', 'phone' => '051', 'email' => 'one@ex.com'],
                    ['name' => 'Two', 'phone' => '', 'email' => ''],
                ],
            ])
            ->assertRedirect(route('back.training-disclosure.requests.show', $request));

        $request->refresh();
        $this->assertSame('New Co', $request->company_name);
        $this->assertSame(2, $request->trainees_count);
        $this->assertCount(2, $request->trainees_draft);

        $this->actingAs($admin)
            ->delete(route('back.training-disclosure.requests.destroy', $request))
            ->assertRedirect(route('back.training-disclosure.requests.index'));

        $this->assertNull(TrainingDisclosureRequest::query()->find($request->id));
    }
}
