<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'manage-recorded-courses',
        ]);

        foreach (Team::query()->cursor() as $team) {
            $role = Role::query()
                ->where('team_id', $team->id)
                ->where('name', $team->id.'_admins')
                ->first();

            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        $permission = Permission::query()->where('name', 'manage-recorded-courses')->first();

        if (! $permission) {
            return;
        }

        foreach (Role::query()->cursor() as $role) {
            if ($role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
            }
        }

        $permission->delete();
    }
};
