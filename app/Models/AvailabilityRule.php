<?php

namespace App\Models;

use Database\Factories\AvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRule extends Model
{
    /** @use HasFactory<AvailabilityRuleFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'schedule_id',
        'day_of_week',
        'start_minute',
        'end_minute',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_minute' => 'integer',
            'end_minute' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
