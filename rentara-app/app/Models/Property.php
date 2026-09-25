<?php

namespace App\Models;

use App\IdentityDocumentType;
use App\ListingStatus;
use App\PropertyType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'property_type',
        'description',
        'full_address',
        'district',
        'city',
        'identity_document_requirements',
        'booking_expiry_days',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'identity_document_requirements' => 'array',
            'booking_expiry_days' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /** @return HasMany<PropertyPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class);
    }

    /** @return HasOne<Listing, $this> */
    public function listing(): HasOne
    {
        return $this->hasOne(Listing::class);
    }

    public function invalidateListingReview(): void
    {
        $this->listing()->first()?->resetForMaterialChange();
    }

    public function ensureDraftListing(): Listing
    {
        return $this->listing()->firstOrCreate([], [
            'slug' => Str::slug($this->name).'-'.$this->id,
            'status' => ListingStatus::Draft,
        ]);
    }

    /** @return array<int, string> */
    public function requiredIdentityDocumentTypes(): array
    {
        return $this->identity_document_requirements ?? [IdentityDocumentType::Ktp->value];
    }

    public function createDraftListing(): Listing
    {
        return $this->listing()->create([
            'slug' => Str::slug($this->name).'-'.$this->id,
            'status' => ListingStatus::Draft,
        ]);
    }
}
