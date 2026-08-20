<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MovieTicketAppliedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;
    public $employee;

    public function __construct($ticket, $employee)
    {
        $this->ticket = $ticket;
        $this->employee = $employee;
    }

    public function build()
    {
        $employeeName = trim($this->employee->name . ' ' . ($this->employee->last_name ?? ''));
        $monthYear = \Carbon\Carbon::parse($this->ticket->date)->format('F Y');

        return $this->from(config('mail.from.address'), $employeeName)
            ->subject('New Movie Ticket Submitted - ' . $employeeName . ' - ' . $monthYear)
            ->markdown('emails.movie_ticket_applied')
            ->with([
                'ticket' => $this->ticket,
                'employee' => $this->employee,
            ]);
    }
}
