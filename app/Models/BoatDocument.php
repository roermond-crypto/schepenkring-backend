<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoatDocument extends Model
{
    protected $fillable = ['boat_id', 'user_id', 'file_path', 'file_type', 'document_type', 'sort_order', 'uploaded_at', 'verified'];

    public function boat()
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
