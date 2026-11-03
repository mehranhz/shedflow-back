<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uid',
        'organization_id',
        'event_type_id',
        'host_user_id',
        'customer_id',
        'start_at',
        'end_at',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'timezone',
        'status',
        'source',
        'location_type',
        'location_value',
        'answers',
        'cancellation_reason',
        'rescheduled_from_id',
        'hold_expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'location_type' => LocationType::class,
            'answers' => 'array',
            'metadata' => 'array',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function actionTokens(): HasMany
    {
        return $this->hasMany(SignedActionToken::class);
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(BookingPaymentReceipt::class);
    }

    /**
     * Bookings that currently block a calendar slot (excludes expired payment holds).
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeOccupyingSlot(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', [
                BookingStatus::PendingConfirmation->value,
                BookingStatus::Confirmed->value,
            ])->orWhere(function (Builder $pending) {
                $pending->where('status', BookingStatus::PendingPayment->value)
                    ->where(function (Builder $hold) {
                        $hold->whereNull('hold_expires_at')
                            ->orWhere('hold_expires_at', '>', now());
                    });
            });
        });
    }
}
