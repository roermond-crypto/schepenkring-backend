<?php

namespace App\Mail;

use App\Models\HarborWidgetDailySnapshot;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HarborWidgetIssueMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $harbor;
    public HarborWidgetDailySnapshot $snapshot;

    public function __construct(User $harbor, HarborWidgetDailySnapshot $snapshot)
    {
        $this->harbor = $harbor;
        $this->snapshot = $snapshot;
    }

    public function build(): self
    {
        return $this
            ->subject('Harbor Widget Issue Detected')
            ->view('emails.harbor_widget_issue');
    }
}
