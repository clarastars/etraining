<?php
/**
 * Copyright (c) 2020 - Clarastars, LLC  - All Rights Reserved.
 *
 * Unauthorized copying of this file via any medium is strictly prohibited.
 * This file is a proprietary of Clarastars LLC and is confidential / educational purpose only.
 *
 * https://clarastars.com - info@clarastars.com
 * @author Shafiq al-Shaar <shafiqalshaar@gmail.com>
 */

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use Log;

class RolesService
{
    public $permissions = [
        'view-companies',
        'create-companies',
        'edit-companies',
        'delete-companies',
        'view-company-contracts',
        'create-company-contracts',
        'edit-company-contracts',
        'delete-company-contracts',
        'view-financial-department',
        'approve-invoices',
        'approve-instructor-applicants',
        'edit-permissions',
        'view-permissions',
        'download-attendance-sheet-for-course-batch',
        'view-company-trainees',
        'reset-trainees-account-password',
        'can-impersonate',
        'send-messages-to-groups-of-trainees',
        'view-backoffice-reports',
        'disable-website',
        'edit-survey-links',
        'edit-complaint-settings',
        'issue-monthly-invoices',
        'approve-payment-receipt',
        'approve-invoice-paid',
        'edit-payment-settings',
        'delete-invoice',
        'can-delete-invoice-anytime',
        'submit-company-attendance-report',
        'receive-notification-on-trainee-delete',
        'manage-missed-course-notices',
        'override-training-costs',
        'companies-report',
        'manage-trainee-groups',
        'block-trainee',
        'view-dashboard-counters',
        'view-deleted-companies',
        'restore-deleted-companies',
        'view-orders',
        'accept-reject-new-email',
        'manage-resignations',
        'edit-invoice-amount',
        'edit-invoice-company',
        // add the same name of the role to translation files.
        'restrict-user-companies-settings',
        'view-all-companies',
        'view-gosi',
        'edit-date-period',
        'can-create-recruitment-companies',
        'search-trainees-by-identity',
        'view-trainee-identity-only',
        'manage-recorded-courses',
    ];

    public $instructorPermissions = [
        'create-personal-courses',
        'view-personal-courses',
        'archive-personal-courses',
        'broadcast-personal-courses',
    ];

    public function seedPermissions()
    {
        Log::info('Beginning to seed permissions');
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
            ]);
        }
        foreach ($this->instructorPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
            ]);
        }
        Log::info('Completed seeding permissions');
    }

    /**
     *
     * @param \App\Models\Team $team
     */
    public function seedRolesToTeam(Team $team)
    {
        $this->seedPermissions();

        // Role.
        $adminRole = Role::firstOrCreate(['name' => $team->id.'_admins', 'team_id' => $team->id]);
        $adminRole->givePermissionTo($this->permissions);

        Log::info('Created "Admin" role for team ID: '.$team->id);

        // Newly created user.
        if ($owner = $team->owner) {
            Log::info('Assigning admin role to the user ID of: '.$owner->id.' and under team ID: '.$team->id);
            $owner->assignRole($adminRole);
        }

        // Add default roles.
        Role::firstOrCreate(['name' => $team->id.'_trainees', 'team_id' => $team->id]);
        $instructor = Role::firstOrCreate(['name' => $team->id.'_instructors', 'team_id' => $team->id]);
        $instructor->givePermissionTo($this->instructorPermissions);
        Role::firstOrCreate(['name' => $team->id.'_finance', 'team_id' => $team->id]);
        Role::firstOrCreate(['name' => $team->id.'_chasers', 'team_id' => $team->id]);
        Role::firstOrCreate(['name' => $team->id.'_services', 'team_id' => $team->id]);
        Role::firstOrCreate(['name' => $team->id.'_services_manager', 'team_id' => $team->id]);
        Role::firstOrCreate(['name' => $team->id.'_backend', 'team_id' => $team->id]);

    }
}
