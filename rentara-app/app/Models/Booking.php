<?php

namespace App\Models;

use App\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $fillable = [
        'rental_application_id', 'organization_id', 'unit_id', 'requested_move_in',
        'start_date', 'end_date', 'terms_snapshot', 'status', 'expires_at', 'booking_expiry_days',
        'expired_at', 'expiry_reason', 'expired_by',
        'payment_amount', 'payment_reference', 'payment_note', 'confirmed_by', 'confirmed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_move_in' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'terms_snapshot' => 'array',
            'status' => BookingStatus::class,
            'expires_at' => 'datetime',
            'booking_expiry_days' => 'integer',
            'expired_at' => 'datetime',
            'payment_amount' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RentalApplication, $this> */
    public function rentalApplication(): BelongsTo
    {
        return $this->belongsTo(RentalApplication::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return HasOne<Tenancy, $this> */
    public function tenancy(): HasOne
    {
        return $this->hasOne(Tenancy::class);
    }
}
