<?php

namespace App\Models;

use Database\Factories\AvailabilityOverrideFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityOverride extends Model
{
    /** @use HasFactory<AvailabilityOverrideFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'schedule_id',
        'date',
        'is_unavailable',
        'start_minute',
        'end_minute',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_unavailable' => 'boolean',
            'start_minute' => 'integer',
            'end_minute' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
