<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProjectAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $project;
    public $managerName;

    public function __construct($project, $managerName)
    {
        $this->project = $project;
        $this->managerName = $managerName;
    }

    public function build()
    {
        return $this->markdown('emails.project_assigned')
            ->subject('You have been assigned to a new project')
            ->with([
                'project' => $this->project,
                'managerName' => $this->managerName,
            ]);
    }
}