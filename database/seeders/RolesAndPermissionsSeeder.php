<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'Administrator',
            'Employee',
            'Team Lead',
            'Management',
            'Accounting',
        ];

        // Define permissions
        $permissions = [
            'add_project',
            'edit_project',
            'view_project',
            'delete_project',
            'assign_project',
            'add_employee',
            'edit_employee',
            'view_employee',
            'delete_employee',
            'add_task',
            'edit_task',
            'view_task',
            'delete_task',
            'assign_task',
            'add_timesheet',
            'edit_timesheet',
            'view_timesheet',
            'delete_timesheet',
            'add_client',
            'edit_client',
            'view_client',
            'delete_client',
            'add_leave',
            'edit_leave',
            'view_leave',
            'delete_leave',
            'add_holiday',
            'edit_holiday',
            'view_holiday',
            'delete_holiday',
            'add_notice',
            'edit_notice',
            'view_notice',
            'delete_notice',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
