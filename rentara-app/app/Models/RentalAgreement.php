<?php

namespace App\Models;

use Database\Factories\RentalAgreementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalAgreement extends Model
{
    /** @use HasFactory<RentalAgreementFactory> */
    use HasFactory;

    protected $fillable = [
        'rental_application_id', 'version', 'terms_snapshot', 'contract_storage_path',
        'contract_original_name', 'contract_mime_type', 'contract_byte_size',
        'organization_approved_by', 'organization_approved_at', 'applicant_approved_by',
        'applicant_approved_at', 'approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'terms_snapshot' => 'array',
            'version' => 'integer',
            'contract_byte_size' => 'integer',
            'organization_approved_at' => 'datetime',
            'applicant_approved_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RentalApplication, $this> */
    public function rentalApplication(): BelongsTo
    {
        return $this->belongsTo(RentalApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function organizationApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organization_approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function applicantApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_approved_by');
    }
}
