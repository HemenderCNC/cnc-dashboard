<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PermissionModule;

class PermissionModuleSeeder extends Seeder
{
    public function run()
    {
        $modules = [
            ['name' => 'Project', 'slug' => 'project'],
            ['name' => 'Employee', 'slug' => 'employee'],
            ['name' => 'Task', 'slug' => 'task'],
        ];

        foreach ($modules as $module) {
            PermissionModule::firstOrCreate(['slug' => $module['slug']], $module);
        }
    }
}
