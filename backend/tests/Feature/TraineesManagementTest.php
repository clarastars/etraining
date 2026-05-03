<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Jetstream\AddTeamMember;
use App\Models\Back\Company;
use App\Models\Back\Instructor;
use App\Models\Back\RequiredTraineesFiles;
use App\Models\Back\Trainee;
use App\Models\Back\TraineeGroup;
use App\Models\City;
use App\Models\EducationalLevel;
use App\Models\MaritalStatus;
use App\Models\User;
use App\Notifications\TraineePrivateMessage;
use App\Notifications\TraineeSetupAccountNotification;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Storage;
use Tests\TestCase;

class TraineesManagementTest extends TestCase
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

    public function make_trainee()
    {
        // Make a new team.
        $admin = User::factory()->create();
        $team = $admin->ownedTeams()->create([
            'name' => 'eTraining Shafiq',
            'personal_team' => false,
        ]);
        app()->make(RolesService::class)->seedRolesToTeam($team);

        return [
            'name' => 'Davy Jones',
            'english_name' => 'Davy Jones',
            'email' => 'davy.jones@gmail.com',
            'identity_number' => '2020202010',
            'birthday' => '1994-12-10',
            'phone' => '501234567',
            'phone_additional' => '501234568',
            'educational_level_id' => EducationalLevel::factory()->create()->id,
            'city_id' => City::create(['name' => 'Riyadh'])->id,
            'marital_status_id' => MaritalStatus::factory()->create()->id,
            'password' => 'password',
            'password_confirmation' => 'password',
            'children_count' => 1,
        ];
    }

    public function test_user_can_access_trainee_management_page()
    {
        $this->actingAs($this->user)
            ->get(route('back.trainees.index'))
            ->assertSuccessful();
    }

    public function test_user_can_access_engineers_list_page()
    {
        $this->actingAs($this->user)
            ->get(route('back.trainees.engineers.index'))
            ->assertSuccessful();
    }

    public function test_user_can_see_new_trainee_form()
    {
        $this->actingAs($this->user)
            ->get(route('back.trainees.create'))
            ->assertSuccessful();
    }

    public function test_user_can_create_a_trainee()
    {
        $trainee = [
            'name' => 'Shafiq al-Shaar',
            'english_name' => 'Shafiq al-Shaar',
            'identity_number' => '10000',
            'phone' => '501234567',
            'phone_additional' => '501234568',
            'birthday' => '1994-12-10',
            'educational_level_id' => null,
            'city_id' => null,
            'marital_status_id' => null,
            'children_count' => 0,
        ];

        $this->actingAs($this->user)
            ->post(route('back.trainees.store'), $trainee)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('trainees', $trainee);
    }

    public function test_user_can_view_trainee()
    {
        $trainee = Trainee::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('back.trainees.show', $trainee->id))
            ->assertPropValue('trainee', function ($savedTrainee) use ($trainee) {
                $this->assertEquals($savedTrainee['name'], $trainee['name']);
            });
    }

    public function test_uploading_and_deleting_identity_file()
    {
        Storage::fake('s3');

        $trainee = Trainee::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('back.trainees.attachments.identity', ['trainee_id' => $trainee->id]), [
                'file' => UploadedFile::fake()->create('identity-copy.jpg', 1024 * 24),
            ])
            ->assertSessionHasNoErrors()
            ->assertSuccessful();

        $this->assertDatabaseHas('media', [
            'model_id' => $trainee->id,
            'file_name' => 'identity-copy.jpg',
        ]);

        $this->actingAs($this->user)
            ->delete(route('back.trainees.attachments.identity.destroy', ['trainee_id' => $trainee->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('media', [
            'model_id' => $trainee->id,
            'file_name' => 'identity-copy.jpg',
        ]);
    }

    public function test_uploading_and_deleting_qualification_file()
    {
        Storage::fake('s3');

        $trainee = Trainee::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('back.trainees.attachments.qualification', ['trainee_id' => $trainee->id]), [
                'file' => UploadedFile::fake()->create('qualification-copy.jpg', 1024 * 24)
            ])
            ->assertSessionHasNoErrors()
            ->assertSuccessful();

        $this->assertDatabaseHas('media', [
            'model_id' => $trainee->id,
            'file_name' => 'qualification-copy.jpg',
        ]);

        $this->actingAs($this->user)
            ->delete(route('back.trainees.attachments.qualification.destroy', ['trainee_id' => $trainee->id]));

        $this->assertDatabaseMissing('media', [
            'model_id' => $trainee->id,
            'file_name' => 'qualification-copy.jpg',
        ]);
    }

    public function test_uploading_and_deleting_bank_account_file()
    {
        Storage::fake('s3');

        $trainee = Trainee::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('back.trainees.attachments.bank-account', ['trainee_id' => $trainee->id]), [
                'file' => UploadedFile::fake()->create('bank-account-copy.jpg', 1024 * 24)
            ])
            ->assertSessionHasNoErrors()
            ->assertSuccessful();

        $this->assertDatabaseHas('media', [
            'model_id' => $trainee->id,
            'file_name' => 'bank-account-copy.jpg',
        ]);

        $this->actingAs($this->user)
            ->delete(route('back.trainees.attachments.bank-account.destroy', ['trainee_id' => $trainee->id]));

        $this->assertDatabaseMissing('media', [
            'model_id' => $trainee->id,
            'file_name' => 'bank-account-copy.jpg',
        ]);
    }

    public function test_linking_a_trainee_to_an_instructor()
    {
        app()->setLocale('en');

        $instructor = Instructor::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $trainee = Trainee::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $trainee_2 = Trainee::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $data = [
            'instructor_id' => $instructor->id,
            'trainees' => [
                $trainee->id,
                $trainee_2->id,
            ],
        ];

        $this->actingAs($this->user)
            ->post(route('back.trainees.assign-instructor'), $data)
            ->assertRedirect();

        $this->assertDatabaseHas('trainees', [
            'id' => $trainee->id,
            'instructor_id' => $instructor->id,
        ]);

        $this->assertDatabaseHas('trainees', [
            'id' => $trainee_2->id,
            'instructor_id' => $instructor->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('back.trainees.assign-instructor'), [
                'instructor_id' => $instructor->id,
                'trainees' => [
                    $trainee->id,
                ]
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('trainees', [
            'id' => $trainee->id,
            'instructor_id' => $instructor->id,
        ]);

        $this->assertDatabaseHas('trainees', [
            'id' => $trainee_2->id,
            'instructor_id' => null,
        ]);
    }

    public function test_getting_groups_names()
    {
        $nancy = $this->user;

        $company = Company::factory()->create(['team_id' => $nancy->personalTeam()->id]);

        $teamX = TraineeGroup::factory()->create(['company_id' => $company->id, 'team_id' => $company->team_id]);
        $alex = Trainee::factory()->create(['company_id' => $company->id, 'team_id' => $company->team_id, 'trainee_group_id' => $teamX]);
        $mike = Trainee::factory()->create(['company_id' => $company->id, 'team_id' => $company->team_id, 'trainee_group_id' => $teamX]);

        $teamB = TraineeGroup::factory()->create(['company_id' => $company->id, 'team_id' => $company->team_id]);
        $jonas = Trainee::factory()->create(['company_id' => $company->id, 'team_id' => $company->team_id, 'trainee_group_id' => $teamB]);
        $steve = Trainee::factory()->create(['company_id' => $company->id, 'team_id' => $company->team_id, 'trainee_group_id' => $teamB]);

        $this->actingAs($nancy)
            ->get('/api/back/trainee-groups')
            ->assertJson([
                [
                    'name' => $teamX->name,
                    //'trainees' => [
                    //    ['id' => $alex->id],
                    //    ['id' => $mike->id],
                    //]
                ],
                [
                    'name' => $teamB->name,
                    //'trainees' => [
                    //    ['id' => $jonas->id],
                    //    ['id' => $steve->id],
                    //]
                ],
            ]);
    }

    public function test_training_application_settings_page()
    {
        $nancy = $this->user;
        $this->actingAs($nancy)
            ->get(route('back.settings.trainees-application'))
            ->assertSuccessful();
    }

    public function test_training_application_required_files()
    {
        $nancy = $this->user;

        $file = new RequiredTraineesFiles();
        $file->name_en = 'Passport Image';
        $file->name_ar = 'صورة جواز سفر';
        $file->required = false;
        $file->team_id = $nancy->personalTeam()->id;
        $file->save();

        $this->actingAs($nancy)
            ->get(route('back.settings.trainees-application.required-files'))
            ->assertSuccessful()
            ->assertSee($file->name_en);
    }

    public function test_save_training_application_required()
    {
        $nancy = $this->user;

        $file = new RequiredTraineesFiles();
        $file->name_en = 'Passport Image';
        $file->name_ar = 'صورة جواز سفر';
        $file->required = false;
        $file->team_id = $nancy->personalTeam()->id;
        $file->save();

        $this->actingAs($nancy)
            ->post(route('back.settings.trainees-application.required-files'), [
                'name_en' => 'Passport Image',
                'name_ar' => 'صورة جواز السفر',
            ]);

        $this->actingAs($nancy)
            ->get(route('back.settings.trainees-application.required-files'))
            ->assertSuccessful()
            ->assertSee($file->name_en);
    }

    public function test_delete_trainee_application_requirement()
    {
        $nancy = $this->user;

        $file = new RequiredTraineesFiles();
        $file->name_en = 'Passport Image';
        $file->name_ar = 'صورة جواز سفر';
        $file->required = false;
        $file->team_id = $nancy->personalTeam()->id;
        $file->save();

        $this->actingAs($nancy)
            ->delete(route('back.settings.trainees-application.required-files.delete', ['id' => $file->id]));

        $this->actingAs($nancy)
            ->get(route('back.settings.trainees-application.required-files'))
            ->assertDontSee($file->name_en);
    }

    public function test_trainee_is_shown_as_pending_completing_application()
    {
        $admin = $this->user;

        $trainee_input = $this->make_trainee();

        $this->post(route('register.trainees'), $trainee_input);
        $trainee = Trainee::whereEmail($trainee_input['email'])->first();

        $this->actingAs($admin)
            ->get(route('back.trainees.index'))
            ->assertSuccessful()
            ->assertPropValue('trainees', function ($trainees) use ($trainee) {
                $this->assertContains($trainee->email, $trainees['data'][0]);
                $this->assertTrue($trainees['data'][0]['is_pending_uploading_files']);
            });
    }

    public function test_trainee_marked_as_pending_approval_when_they_finish_uploading_their_cv()
    {
        $trainee_input = $this->make_trainee();

        $this->post(route('register.trainees'), $trainee_input);
        $trainee = Trainee::whereEmail($trainee_input['email'])->first();
        $this->actingAs($trainee->user)
            ->post(route('api.register.trainees.upload-cv'), [
                'identity_card_copy' => UploadedFile::fake()->create('identity-card-copy.jpg', 1024 * 24),
                'qualification_copy' => UploadedFile::fake()->create('qualification-copy.jpg', 1024 * 24),
                'bank_account_copy' => UploadedFile::fake()->create('bank-account-copy.jpg', 1024 * 24),
            ])->assertSuccessful();

        $this->assertDatabaseHas('trainees', [
            'id' => $trainee->id,
            'status' => Trainee::STATUS_PENDING_APPROVAL,
        ]);

        $trainee->refresh();
        $this->assertTrue($trainee->is_pending_approval);
    }

    public function test_approving_trainee_to_access_the_platform()
    {
        $admin = $this->user;

        $shafiqUser = User::factory()->create([
            'email' => 'shafiqalshaar@gmail.com',
        ]);

        $team = $this->user->currentTeam;
        (new AddTeamMember())->add($shafiqUser, $team, $shafiqUser->email, 'instructor');

        $shafiqUser->current_team_id = $team->id;
        $shafiqUser->save();

        $shafiqProfile = Trainee::factory()->create([
            'team_id' => $shafiqUser->current_team_id,
            'user_id' => $shafiqUser->id,
            'email' => $shafiqUser->email,
            'status' => Instructor::STATUS_PENDING_APPROVAL,
        ]);

        $this->actingAs($admin)
            ->post(route('back.trainees.approve-user', $shafiqProfile->id))
            ->assertRedirect(route('back.trainees.show', $shafiqProfile->id));

        $this->assertDatabaseHas('trainees', [
            'id' => $shafiqProfile->id,
            'status' => Instructor::STATUS_APPROVED,
            'approved_by_id' => $admin->id,
        ]);

        $this->assertDatabaseMissing('trainees', [
            'approved_at' => null,
        ]);
    }

    public function test_admin_can_import_users()
    {
        $admin = $this->user;
        $this->actingAs($admin)
            ->get(route('back.trainees.import'))
            ->assertSuccessful();
    }

    public function test_updating_trainee_email_changes_the_user_email_too()
    {
        $admin = $this->user;

        $trainee_input = $this->make_trainee();

        $this->post(route('register.trainees'), $trainee_input);
        $trainee = Trainee::whereEmail($trainee_input['email'])->first();

        $this->actingAs($admin)
            ->get(route('back.trainees.index'))
            ->assertSee($trainee_input['email']);

        $newEmail = $this->faker()->email;

        $this->actingAs($admin)
            ->put(route('back.trainees.update', $trainee->id), [
                'name' => $this->faker->name,
                'identity_number' => '123',
                'email' => $newEmail,
                'phone' => '+966565176235',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('trainees', [
            'email' => $newEmail,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => $newEmail,
        ]);
    }

    public function test_updating_trainee_email_with_an_existing_user_email_fails()
    {
        $existingUser = User::factory()->create();

        $admin = $this->user;
        $trainee_input = $this->make_trainee();
        $this->post(route('register.trainees'), $trainee_input);
        $trainee = Trainee::whereEmail($trainee_input['email'])->first();

        $this->actingAs($admin)
            ->put(route('back.trainees.update', $trainee->id), [
                'name' => $this->faker->name,
                'identity_number' => '123',
                'email' => $existingUser->email,
            ])
            ->assertSessionHasErrorsIn('email');
    }

    public function test_admin_re_sending_invitation_notification_to_trainee()
    {
        Notification::fake();

        $admin = $this->user;
        $trainee_input = $this->make_trainee();
        $this->post(route('register.trainees'), $trainee_input);
        $trainee = Trainee::whereEmail($trainee_input['email'])->first();
        $this->actingAs($admin)->post(route('back.trainees.approve-user', $trainee->id));

        $this->actingAs($admin)
            ->post(route('back.trainees.re-send-invitation', $trainee->id))
            ->assertRedirect();

        Notification::assertSentTo($trainee->user, TraineeSetupAccountNotification::class);
    }

    public function test_admin_setting_trainee_password()
    {
        $admin = $this->user;

        $trainee_input = $this->make_trainee();
        $this->post(route('register.trainees'), $trainee_input);
        $trainee = Trainee::whereEmail($trainee_input['email'])->first();
        $this->actingAs($admin)->post(route('back.trainees.approve-user', $trainee->id));

        $oldHash = $trainee->refresh()->user;

        $this->actingAs($admin)
            ->post(route('back.trainees.set-password', $trainee->id), [
                'password' => 'hi_there_mate',
            ]);

        $this->assertNotEquals($oldHash, $trainee->user->refresh()->password);
    }

    public function test_sending_private_message_to_trainee()
    {
        Notification::fake();

        $mike = Trainee::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $message = [
            'email_title' => 'Hello1',
            'email_body' => 'Hello2',
            'sms_body' => 'Hello3',
        ];

        $this->actingAs($this->user)
            ->post(route('back.trainees.private-notifications.send', $mike->id), $message)
            ->assertRedirect(route('back.trainees.show', $mike->id))
            ->assertSessionDoesntHaveErrors();

        Notification::assertSentTo($mike, function(TraineePrivateMessage $notification) use ($message) {
            return $notification->email_body === $message['email_body'];
        });
    }

    public function test_sending_private_notification_form()
    {
        $sarah = Trainee::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('back.trainees.private-notifications.create', $sarah->id))
            ->assertSuccessful();
    }
}
