<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAuthorization extends Model
{
    protected $fillable = ['user_id', 'operation_name'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}