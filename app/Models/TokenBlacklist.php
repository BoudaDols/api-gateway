<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenBlacklist extends Model
{
    public $timestamps = false;

    protected $table = 'token_blacklist';

    protected $fillable = ['token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
