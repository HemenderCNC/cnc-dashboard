<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeaveStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $leave;
    public $employee;
    public $status;
    public $actionBy;

    public function __construct($leave, $employee, $status, $actionBy)
    {
        $this->leave = $leave;
        $this->employee = $employee;
        $this->status = $status;
        $this->actionBy = $actionBy;
    }

    public function build()
    {
        $subject = 'Leave Request ' . ucfirst($this->status);
        
        return $this->from(config('mail.from.address'), config('app.name'))
            ->markdown('emails.leave_status')
            ->subject($subject)
            ->with([
                'leave' => $this->leave,
                'employee' => $this->employee,
                'status' => $this->status,
                'actionBy' => $this->actionBy,
            ]);
    }
}
