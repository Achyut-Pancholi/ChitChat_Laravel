<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    //
    protected $fillable = ['user_id', 'receiver_id', 'body'];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
