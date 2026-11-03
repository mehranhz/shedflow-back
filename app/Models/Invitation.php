<?php

namespace App\Models;

use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'phone',
        'role',
        'token_hash',
        'invited_by_id',
        'expires_at',
        'accepted_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function toPublicArray(?string $rawToken = null): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'phone' => $this->phone,
            'role' => $this->role,
            'expiresAt' => optional($this->expires_at)?->toIso8601String(),
            'createdAt' => optional($this->created_at)?->toIso8601String(),
            ...($rawToken ? ['token' => $rawToken] : []),
        ];
    }
}
