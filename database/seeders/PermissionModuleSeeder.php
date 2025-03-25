<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PermissionModule;
use Illuminate\Support\Str;

class PermissionModuleSeeder extends Seeder
{
    public function run()
    {
        $modules = [
            'Employee Permissions',
            'Project Permissions',
            'Task Permissions',
            'Timesheet Permissions',
            'Client Permissions',
            'Leave Permissions',
            'Holiday Permissions',
            'Notice Permissions',
            'Movie Ticket Permissions',
            'Role Permissions',
            'Permission Permissions',
            'Task Status',
            'Milestone Permissions',
            'Languages Permissions',
            'Platforms Permissions',
            'Document Types Permissions',
            'Project Status Permissions',
            'Employee Status Permissions',
            'Work Location Permissions',
            'Employee Types Permissions',
            'Skills Permissions',
            'Qualifications Permissions',
            'Designations Permissions',
            'Industry Types Permissions',
            'Countries Permissions',
            'Departments Permissions',
            'Users Permissions',
            'Task Type Permissions',
            'General setting Permissions',
        ];

        foreach ($modules as $module) {
            PermissionModule::updateOrCreate(
                ['slug' => Str::slug($module, '_')], // Ensure uniqueness
                [
                    'name' => $module,
                    'slug' => Str::slug($module, '_')
                ]
            );
        }

        $this->command->info('✅ Permission modules updated successfully!');
    }
}

