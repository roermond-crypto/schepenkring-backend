<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoatChecklistStatus extends Model
{
    protected $fillable = ['boat_id', 'checklist_item_id', 'user_id', 'status', 'completed_at'];

    public function boat()
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }

    public function item()
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
