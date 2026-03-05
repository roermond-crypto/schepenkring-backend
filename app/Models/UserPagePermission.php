<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPagePermission extends Model
{
    protected $fillable = ['user_id', 'page_permission_id', 'permission_value'];
    
    public function page()
    {
        return $this->belongsTo(PagePermission::class, 'page_permission_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}