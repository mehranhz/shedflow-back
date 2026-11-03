<?php

namespace App\Models;

use App\Enums\PaymentReceiptStatus;
use Database\Factories\BookingPaymentReceiptFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingPaymentReceipt extends Model
{
    /** @use HasFactory<BookingPaymentReceiptFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'organization_id',
        'uploaded_by_user_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'status',
        'reviewer_user_id',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentReceiptStatus::class,
            'size_bytes' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
