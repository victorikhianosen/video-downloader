<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleToken extends Model
{
      protected $table = 'google_token';

    protected $fillable = [
        'access_token', 'refresh_token', 'expires_at'
    ];

    protected $dates = ['expires_at'];

    // helper to get the single row
    public static function instance()
    {
        return static::first();
    }
}
