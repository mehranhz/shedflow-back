<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OTP extends Model
{
    use HasUuids;
    //
    protected $fillable = [
        'username',
        "code",
        "expires_at",
        "verified_at",
    ];
}
