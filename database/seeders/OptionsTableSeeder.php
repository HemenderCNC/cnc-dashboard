<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OptionsTableSeeder extends Seeder
{
    public function run()
    {
        $options = [
            // Qualification Levels
            ['category' => 'qualification_levels', 'value' => 'High School'],
            ['category' => 'qualification_levels', 'value' => 'Diploma'],
            ['category' => 'qualification_levels', 'value' => 'Bachelor\'s Degree'],
            ['category' => 'qualification_levels', 'value' => 'Master\'s Degree'],
            ['category' => 'qualification_levels', 'value' => 'Doctorate (Ph.D.)'],
            ['category' => 'qualification_levels', 'value' => 'Certification'],

            // Employment Types
            ['category' => 'employment_types', 'value' => 'Full-Time'],
            ['category' => 'employment_types', 'value' => 'Part-Time'],
            ['category' => 'employment_types', 'value' => 'Freelance'],
            ['category' => 'employment_types', 'value' => 'On Contract'],

            // Work Locations
            ['category' => 'work_locations', 'value' => 'remote'],
            ['category' => 'work_locations', 'value' => 'in-office'],
            ['category' => 'work_locations', 'value' => 'hybrid'],

            // Employee Statuses
            ['category' => 'employee_statuses', 'value' => 'Active'],
            ['category' => 'employee_statuses', 'value' => 'On Probation'],
            ['category' => 'employee_statuses', 'value' => 'Terminated'],
            ['category' => 'employee_statuses', 'value' => 'Former Employee'],
            ['category' => 'employee_statuses', 'value' => 'On Sabbatical'],

            // Departments
            ['category' => 'departments', 'value' => 'IT Department'],
            ['category' => 'departments', 'value' => 'Development'],
            ['category' => 'departments', 'value' => 'Design'],
            ['category' => 'departments', 'value' => 'Quality Assurance'],

            // Designations
            ['category' => 'designations', 'group' => 'Development', 'value' => 'Software Engineer'],
            ['category' => 'designations', 'group' => 'Development', 'value' => 'Senior Software Engineer'],
            ['category' => 'designations', 'group' => 'Development', 'value' => 'Lead Developer'],
        ];

        DB::table('options')->insert($options);
    }
}