<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $leave;
    public $employee;

    public function __construct($leave, $employee)
    {
        $this->leave = $leave;
        $this->employee = $employee;
    }

    public function build()
    {
        $leaveType = $this->leave->leave_type ?? 'Leave Request';
        $startDate = \Illuminate\Support\Carbon::parse($this->leave->start_date)->format('d-m-Y');

        return $this->from($this->employee->email, $this->employee->name . ' ' . $this->employee->last_name)
            ->markdown('emails.leave_requested')
            ->subject($leaveType . ' - ' . $startDate)
            ->with([
                'leave' => $this->leave,
                'employee' => $this->employee,
            ]);
    }
}
