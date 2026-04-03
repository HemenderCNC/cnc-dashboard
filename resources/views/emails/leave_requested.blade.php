{{-- filepath: resources/views/emails/leave_requested.blade.php --}}
@component('mail::message')
# New Leave Request Generated

Hi,

A new leave request has been submitted. Here are the details:

- **Employee Name:** {{ $employee->name }} {{ $employee->last_name }}
- **Start Date:** {{ $leave->start_date }}
- **End Date:** {{ $leave->end_date }}
- **Duration:** {{ $leave->leave_duration }} day(s)
- **Reason:** {{ $leave->reason }}

Please review and take necessary action from the admin dashboard.

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
