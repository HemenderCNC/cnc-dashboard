{{-- filepath: resources/views/emails/welcome_onboard_employee.blade.php --}}
@component('mail::message')
<p>Dear {{ $user->name }},</p>

<p>Welcome to {{ config('app.name') }}! We are excited to have you on board.</p>

<p>Your account has been created successfully. Here are your login details:</p>

<ul>
    <li><strong>Email:</strong> {{ $user->email }}</li>
    <li><strong>Password:</strong> {{ $user->password ?? '' }}</li>
</ul>

<p>Please log in to your account and complete your profile.</p>

<p>Thank you,</p>
<p>{{ config('app.name') }} Team</p>
@endcomponent