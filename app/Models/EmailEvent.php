<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailEvent extends Model
{
    protected $fillable = [
        'token',
        'campaign_target_id',
        'conversation_id',
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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return bool true the first time this email is opened — callers use
     *              this to avoid posting a "email opened" Chat Hub message
     *              on every repeat pixel load (email clients re-fetch
     *              images on scroll/re-open constantly).
     */
    public function recordOpen(): bool
    {
        $isFirstOpen = $this->opened_at === null;
        $this->opened_at = $this->opened_at ?: now();
        $this->open_count = $this->open_count + 1;
        $this->save();

        return $isFirstOpen;
    }

    /**
     * @return bool true if $url hadn't been clicked before on this email.
     */
    public function recordClick(string $url): bool
    {
        $isNewUrl = ! in_array($url, $this->clicked_urls ?? [], true);
        $this->first_clicked_at = $this->first_clicked_at ?: now();
        $this->click_count = $this->click_count + 1;
        $this->clicked_urls = array_values(array_unique([...($this->clicked_urls ?? []), $url]));
        $this->save();

        return $isNewUrl;
    }
}
