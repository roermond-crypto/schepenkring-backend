<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YachtImage extends Model {
    protected $fillable = [
        'yacht_id', 'url', 'category', 'part_name', 'sort_order',
        // Pipeline fields
        'original_temp_url', 'optimized_master_url', 'thumb_url',
        'original_kept_url', 'status', 'keep_original',
        'quality_score', 'quality_flags', 'original_name',
        'enhancement_method',
    ];

    protected $casts = [
        'keep_original' => 'boolean',
        'quality_flags'  => 'array',
        'quality_score'  => 'integer',
    ];

    protected $appends = ['full_url', 'quality_label', 'optimized_url', 'thumb_full_url'];

    public function yacht(): BelongsTo {
        return $this->belongsTo(Yacht::class);
    }

    // ── Scopes ──

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeReadyForReview($query)
    {
        return $query->where('status', 'ready_for_review');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    // ── Accessors ──

    public function getFullUrlAttribute()
    {
        return asset('storage/' . $this->url);
    }

    public function getOptimizedUrlAttribute()
    {
        if ($this->optimized_master_url) {
            return asset('storage/' . $this->optimized_master_url);
        }
        return $this->full_url;
    }

    public function getThumbFullUrlAttribute()
    {
        if ($this->thumb_url) {
            return asset('storage/' . $this->thumb_url);
        }
        return $this->optimized_url;
    }

    public function getQualityLabelAttribute(): string
    {
        $flags = $this->quality_flags;
        if (!$flags || !is_array($flags)) {
            if ($this->status === 'processing') return '⏳ Processing';
            return '—';
        }

        if (!empty($flags['blurry']))    return '❌ Too blurry';
        if (!empty($flags['too_dark']))   return '⚠️ Low light';
        if (!empty($flags['too_bright'])) return '⚠️ Overexposed';
        if (!empty($flags['low_res']))    return '⚠️ Low resolution';

        if ($this->quality_score >= 70) return '✅ Great';
        if ($this->quality_score >= 50) return '✅ Acceptable';

        return '⚠️ Low quality';
    }
}