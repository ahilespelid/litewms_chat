<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = ['participant_id', 'platform', 'token_hash', 'token'];

    protected $hidden = ['token', 'token_hash'];
}
