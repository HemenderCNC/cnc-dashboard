{{-- filepath: resources/views/emails/leave_status.blade.php --}}
@component('mail::message')
# Leave Request {{ ucfirst($status) }}

Hi {{ $employee->name }},

Your leave request for the following period has been **{{ $status }}**.

- **Leave Type:** {{ $leave->leave_type }}
- **Start Date:** {{ \Carbon\Carbon::parse($leave->start_date)->format('d-M-Y') }}
- **End Date:** {{ \Carbon\Carbon::parse($leave->end_date)->format('d-M-Y') }}
- **Duration:** {{ $leave->leave_duration }} day(s)
- **Action By:** {{ $actionBy->name }} {{ $actionBy->last_name }}

@if($leave->approve_comment)
**Comment:** {{ $leave->approve_comment }}
@endif

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
