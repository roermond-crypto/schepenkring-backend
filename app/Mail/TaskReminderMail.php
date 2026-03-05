<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $actor
    ) {
    }

    public function build(): self
    {
        return $this->subject('Task reminder')
            ->view('emails.task-reminder')
            ->with([
                'task' => $this->task,
                'actor' => $this->actor,
            ]);
    }
}
