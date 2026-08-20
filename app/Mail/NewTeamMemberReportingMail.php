<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewTeamMemberReportingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $manager;
    public $employee;
    public $departmentName;
    public $designationName;

    public function __construct($manager, $employee, $departmentName = '', $designationName = '')
    {
        $this->manager = $manager;
        $this->employee = $employee;
        $this->departmentName = $departmentName;
        $this->designationName = $designationName;
    }

    public function build()
    {
        return $this->markdown('emails.new_team_member_reporting')
            ->subject('New Team Member Assigned to You - ' . ($this->employee->name ?? 'New Employee'))
            ->with([
                'manager' => $this->manager,
                'employee' => $this->employee,
                'departmentName' => $this->departmentName,
                'designationName' => $this->designationName,
            ]);
    }
}
