{{-- filepath: resources/views/emails/project_assigned.blade.php --}}
@component('mail::message')
# New Project Assignment

You have been assigned to a new project. Here are the details:

- **Project Name:** {{ $project->project_name }}
- **Project Industry:** {{ $project->project_industry }}
- **Estimated Start Date:** {{ $project->estimated_start_date }}
- **Estimated End Date:** {{ $project->estimated_end_date }}
- **Priority:** {{ $project->priority }}
- **Project Manager:** {{ $managerName }}

@component('mail::button', ['url' => config('app.front_url') . '/project/info/' . $project->_id])
View Project
@endcomponent

Please log in to your dashboard for more details.

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent