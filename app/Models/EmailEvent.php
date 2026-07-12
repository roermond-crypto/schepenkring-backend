<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailEvent extends Model
{
    protected $fillable = [
        'token',
        'campaign_target_id',
        'email_template_key',
        'recipient_email',
        'sent_at',
        'opened_at',
        'open_count',
        'first_clicked_at',
        'click_count',
        'clicked_urls',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'first_clicked_at' => 'datetime',
        'clicked_urls' => 'array',
    ];

    public function campaignTarget(): BelongsTo
    {
        return $this->belongsTo(CampaignTarget::class);
    }

    public function recordOpen(): void
    {
        $this->opened_at = $this->opened_at ?: now();
        $this->open_count = $this->open_count + 1;
        $this->save();
    }

    public function recordClick(string $url): void
    {
        $this->first_clicked_at = $this->first_clicked_at ?: now();
        $this->click_count = $this->click_count + 1;
        $this->clicked_urls = array_values(array_unique([...($this->clicked_urls ?? []), $url]));
        $this->save();
    }
}
