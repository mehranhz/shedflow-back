<?php

namespace App\Models;

use App\Enums\LocationType;
use Database\Factories\EventTypeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventType extends Model
{
    /** @use HasFactory<EventTypeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'host_user_id',
        'schedule_id',
        'slug',
        'title',
        'description',
        'duration_minutes',
        'location_type',
        'location_value',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'min_notice_minutes',
        'max_days_ahead',
        'slot_interval_minutes',
        'daily_cap',
        'requires_confirmation',
        'requires_payment_receipt',
        'payment_instructions',
        'cancellation_notice_hours',
        'reschedule_notice_hours',
        'questions',
        'is_active',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'location_type' => LocationType::class,
            'questions' => 'array',
            'requires_confirmation' => 'boolean',
            'requires_payment_receipt' => 'boolean',
            'is_active' => 'boolean',
            'is_hidden' => 'boolean',
            'duration_minutes' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'min_notice_minutes' => 'integer',
            'max_days_ahead' => 'integer',
            'slot_interval_minutes' => 'integer',
            'daily_cap' => 'integer',
            'cancellation_notice_hours' => 'integer',
            'reschedule_notice_hours' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
