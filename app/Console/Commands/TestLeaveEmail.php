<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\LeaveRequestedMail;
use App\Models\User;
use App\Models\Leave;

class TestLeaveEmail extends Command
{
    protected $signature = 'mail:test-leave';
    protected $description = 'Send test leave request email to specified recipients';

    public function handle()
    {
        $this->info('Preparing test leave email...');

        // Create a dummy/mock employee object
        $employee = new \stdClass();
        $employee->name = 'Test';
        $employee->last_name = 'Employee';
        $employee->email = 'test.employee@codeandcore.com';

        // Create a dummy/mock leave object
        $leave = new \stdClass();
        $leave->leave_type = 'Privilege Leave';
        $leave->start_date = date('Y-m-d');
        $leave->end_date = date('Y-m-d', strtotime('+1 day'));
        $leave->leave_duration = '1';
        $leave->half_day = 0;
        $leave->half_day_type = null;
        $leave->reason = 'Testing email deliverability with clean template without external images.';

        $recipients = [
            'parthcnc45@gmail.com',
        ];

        $ccRecipients = [
            'patelparth5133@gmail.com',
            'patelparth56653@gmail.com',
            'vijaycnc90@gmail.com'
        ];

        try {
            $this->info('Sending to: ' . implode(', ', $recipients));
            $this->info('CC to: ' . implode(', ', $ccRecipients));
            
            Mail::to($recipients)
                ->cc($ccRecipients)
                ->send(new LeaveRequestedMail($leave, $employee));

            $this->info('Email sent successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
        }
    }
}
