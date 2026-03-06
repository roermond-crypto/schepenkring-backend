<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    protected $fillable = ['template_id', 'title', 'description', 'required', 'position'];

    public function template()
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }
}
