<?php

namespace Database\Seeders;
use App\Models\Project;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Project::updateOrCreate(
            ['name' => 'Branch'], // Replace existing if name matches
            [
                'industry' => 'Software',
                'type' => 'Fixed',
                'budget' => 500,
                'description' => 'Project Description here...',
                'platform_technology' => ['Web', 'Mobile'],
                'programming_languages' => ['PHP', 'JavaScript'],
                'priority' => 'High',
                'status' => 'Active',
                'timeline' => [
                    'estimated_start_date' => '2025-02-01',
                    'estimated_end_date' => '2025-06-01',
                    'actual_start_date' => '2025-02-02',
                    'actual_end_date' => null,
                ],
                'stakeholders' => [
                    'client' => 'Client Name',
                    'assignees' => ['Milan Parekh', 'Divya Patel'],
                    'project_manager' => 'John Doe'
                ],
                'files' => [],
                'milestones' => [
                    [
                        'name' => 'Phase 1',
                        'start_date' => '2025-02-05',
                        'end_date' => '2025-03-05',
                        'color' => 'blue',
                        'status' => 'In Progress'
                    ]
                ]
            ]
        );
    }
}
