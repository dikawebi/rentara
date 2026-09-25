<?php

namespace App\Models;

use App\InvoiceStatus;
use App\InvoiceType;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'tenancy_id', 'organization_id', 'tenant_id', 'type', 'idempotency_key',
        'period_start', 'period_end', 'due_date', 'amount', 'status', 'paid_at',
        'paid_by', 'payment_reference', 'payment_note',
        'payment_evidence_storage_path', 'payment_evidence_mime_type',
        'payment_evidence_byte_size', 'payment_evidence_original_name',
        'payment_evidence_uploaded_by', 'payment_evidence_uploaded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'amount' => 'integer',
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
            'payment_evidence_byte_size' => 'integer',
            'payment_evidence_uploaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenancy, $this> */
    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** @return BelongsTo<User, $this> */
    public function evidenceUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_evidence_uploaded_by');
    }
}
