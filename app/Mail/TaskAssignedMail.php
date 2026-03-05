<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $creator
    ) {
    }

    public function build(): self
    {
        return $this->subject('New task assigned')
            ->view('emails.task-assigned')
            ->with([
                'task' => $this->task,
                'creator' => $this->creator,
            ]);
    }
}
