<?php

namespace App\Models;

use App\ApplicationStatus;
use App\IdentityDocumentType;
use Database\Factories\RentalApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class RentalApplication extends Model
{
    /** @use HasFactory<RentalApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'applicant_id',
        'applicant_snapshot',
        'unit_id',
        'requested_move_in',
        'requested_duration_months',
        'applicant_note',
        'decision_notes',
        'privacy_notice_version',
        'privacy_accepted_at',
        'status',
        'active_application_key',
        'listing_snapshot',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_move_in' => 'date',
            'requested_duration_months' => 'integer',
            'privacy_accepted_at' => 'datetime',
            'status' => ApplicationStatus::class,
            'applicant_snapshot' => 'array',
            'listing_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RentalApplication $application): void {
            $application->active_application_key = $application->status->isActive()
                ? $application->applicant_id.':'.$application->unit_id
                : null;
        });

        static::updated(function (RentalApplication $application): void {
            if ($application->wasChanged('status') && in_array($application->status, [
                ApplicationStatus::Rejected,
                ApplicationStatus::Expired,
            ], true)) {
                $application->identityDocuments()
                    ->whereNull('delete_after')
                    ->update(['delete_after' => now()->addDays(30)]);
            }
        });
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<IdentityDocument, $this> */
    public function identityDocuments(): HasMany
    {
        return $this->hasMany(IdentityDocument::class);
    }

    /** @return HasOne<RentalAgreement, $this> */
    public function rentalAgreement(): HasOne
    {
        return $this->hasOne(RentalAgreement::class);
    }

    /** @return HasOne<Booking, $this> */
    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    /** @return HasOneThrough<Tenancy, Booking, $this> */
    public function tenancy(): HasOneThrough
    {
        return $this->hasOneThrough(Tenancy::class, Booking::class, 'rental_application_id', 'booking_id');
    }

    /** @return array<int, string> */
    public function requiredIdentityDocumentTypes(): array
    {
        return $this->listing_snapshot['identity_document_requirements'] ?? [
            IdentityDocumentType::Ktp->value,
        ];
    }
}
