<?php

namespace App\Models;

use App\ListingStatus;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id',
        'slug',
        'status',
        'rejection_reason',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<RentalApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(RentalApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function resetForMaterialChange(): void
    {
        if ($this->status === ListingStatus::Draft) {
            return;
        }

        $this->forceFill([
            'status' => ListingStatus::Draft,
            'rejection_reason' => null,
            'submitted_by' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'published_at' => null,
        ])->save();
    }

    public function submitForReview(User $user): void
    {
        $this->forceFill([
            'status' => ListingStatus::PendingReview,
            'rejection_reason' => null,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'published_at' => null,
        ])->save();
    }
}
