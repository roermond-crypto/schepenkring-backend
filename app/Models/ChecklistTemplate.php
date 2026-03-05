<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTemplate extends Model
{
    protected $fillable = ['name', 'description', 'boat_type_id', 'active'];

    public function items()
    {
        return $this->hasMany(ChecklistItem::class, 'template_id');
    }
}
