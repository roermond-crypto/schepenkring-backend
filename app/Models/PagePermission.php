<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagePermission extends Model
{
    protected $fillable = ['page_key', 'page_name', 'description'];
    
    public function userPermissions()
    {
        return $this->hasMany(UserPagePermission::class);
    }
}