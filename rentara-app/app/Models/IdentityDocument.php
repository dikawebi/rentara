<?php

namespace App\Models;

use App\IdentityDocumentReviewStatus;
use App\IdentityDocumentType;
use Database\Factories\IdentityDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityDocument extends Model
{
    /** @use HasFactory<IdentityDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'rental_application_id',
        'uploaded_by',
        'document_type',
        'storage_path',
        'mime_type',
        'byte_size',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'delete_after',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => IdentityDocumentType::class,
            'review_status' => IdentityDocumentReviewStatus::class,
            'byte_size' => 'integer',
            'reviewed_at' => 'datetime',
            'delete_after' => 'datetime',
        ];
    }

    /** @return BelongsTo<RentalApplication, $this> */
    public function rentalApplication(): BelongsTo
    {
        return $this->belongsTo(RentalApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
