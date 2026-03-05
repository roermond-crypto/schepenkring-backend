<?php

namespace App\Mail;

use App\Models\HarborWidgetAiAdvice;
use App\Models\HarborWidgetWeeklyMetric;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HarborWidgetAdviceMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $harbor;
    public HarborWidgetWeeklyMetric $metric;
    public HarborWidgetAiAdvice $advice;
    public float $benchmark;

    public function __construct(User $harbor, HarborWidgetWeeklyMetric $metric, HarborWidgetAiAdvice $advice, float $benchmark)
    {
        $this->harbor = $harbor;
        $this->metric = $metric;
        $this->advice = $advice;
        $this->benchmark = $benchmark;
    }

    public function build(): self
    {
        return $this
            ->subject('NauticSecure Button Performance Update')
            ->view('emails.harbor_widget_advice');
    }
}
