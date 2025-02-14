<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class AssignPermissionsToRolesSeeder extends Seeder
{
    public function run()
    {
        // Define permissions for each role
        $rolePermissions = [
            'Administrator' => Permission::all()->pluck('_id')->toArray(), // Store only _id
            'Employee' => [
                Permission::where('name', 'view_project')->first()->_id,
                Permission::where('name', 'add_timesheet')->first()->_id,
                Permission::where('name', 'edit_timesheet')->first()->_id,
                Permission::where('name', 'view_timesheet')->first()->_id,
            ],
            'Team Lead' => [
                Permission::where('name', 'add_task')->first()->_id,
                Permission::where('name', 'edit_task')->first()->_id,
                Permission::where('name', 'view_task')->first()->_id,
                Permission::where('name', 'assign_task')->first()->_id,
                Permission::where('name', 'view_project')->first()->_id,
                Permission::where('name', 'add_employee')->first()->_id,
                Permission::where('name', 'edit_employee')->first()->_id,
            ],
            'Management' => [
                Permission::where('name', 'view_project')->first()->_id,
                Permission::where('name', 'assign_project')->first()->_id,
                Permission::where('name', 'view_employee')->first()->_id,
                Permission::where('name', 'view_task')->first()->_id,
                Permission::where('name', 'view_timesheet')->first()->_id,
            ],
            'Accounting' => [
                Permission::where('name', 'add_timesheet')->first()->_id,
                Permission::where('name', 'edit_timesheet')->first()->_id,
                Permission::where('name', 'view_timesheet')->first()->_id,
                Permission::where('name', 'delete_timesheet')->first()->_id,
            ],
        ];

        // Loop through the roles and assign the permissions by _id
        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                // Store only the permission _id in the permissions field
                $role->permissions = $permissions;
                $role->save();
            }
        }
    }
}
