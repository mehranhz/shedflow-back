<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomerClaimToken extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'email',
        'token_hash',
        'expires_at',
        'used_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
