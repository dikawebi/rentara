<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['workspace_id', 'property_id', 'contract_id', 'tenant_id', 'invoice_number', 'period_start', 'period_end', 'due_date', 'amount', 'currency', 'status', 'paid_at', 'paid_date', 'payment_method', 'payment_reference', 'payment_notes', 'created_by', 'paid_by'];
    protected function casts(): array { return ['status' => InvoiceStatus::class, 'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date', 'amount' => 'integer', 'paid_at' => 'datetime', 'paid_date' => 'date']; }
    public function workspace() { return $this->belongsTo(Workspace::class); }
    public function property() { return $this->belongsTo(Property::class); }
    public function contract() { return $this->belongsTo(RentalContract::class, 'contract_id'); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function payer() { return $this->belongsTo(User::class, 'paid_by'); }
    public function getIsOverdueAttribute(): bool
    {
        $timezone = $this->workspace?->timezone ?: 'Asia/Jakarta';

        return $this->status === InvoiceStatus::Unpaid
            && $this->due_date?->toDateString() < CarbonImmutable::today($timezone)->toDateString();
    }
}
