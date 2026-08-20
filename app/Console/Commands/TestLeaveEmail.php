<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewTeamMemberReportingMail;

class TestLeaveEmail extends Command
{
    protected $signature = 'mail:test-leave';
    protected $description = 'Send test reporting manager notification email to specified recipients';

    public function handle()
    {
        $this->info('Preparing test New Team Member reporting email...');

        // Mock manager object
        $manager = new \stdClass();
        $manager->name = 'Mayur';
        $manager->last_name = 'Soni';
        $manager->email = 'parthcnc45@gmail.com';

        // Mock newly added employee object
        $employee = new \stdClass();
        $employee->name = 'Parth';
        $employee->last_name = 'Patel';
        $employee->employee_id = 'EMP-102';
        $employee->email = 'patelparth5133@gmail.com';
        $employee->joining_date = date('Y-m-d');

        $departmentName = 'Development & Engineering';
        $designationName = 'Senior Full Stack Developer';

        $recipients = [
            'parthcnc45@gmail.com',
        ];

        $ccRecipients = [
            'patelparth5133@gmail.com',
            'patelparth56653@gmail.com',
            'vijaycnc90@gmail.com',
        ];

        try {
            $this->info('Sending to: ' . implode(', ', $recipients));
            $this->info('CC to: ' . implode(', ', $ccRecipients));
            
            Mail::to($recipients)
                ->cc($ccRecipients)
                ->send(new NewTeamMemberReportingMail($manager, $employee, $departmentName, $designationName));

            $this->info('Email sent successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
        }
    }
}
