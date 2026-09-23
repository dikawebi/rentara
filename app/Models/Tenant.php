<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'unit_id', 'name', 'phone', 'email', 'identity_number',
        'date_of_birth', 'gender', 'occupation', 'emergency_contact_name',
        'emergency_contact_phone', 'status',
    ];

    protected function casts(): array
    {
        return [
            'identity_number' => 'encrypted',
            'date_of_birth' => 'encrypted',
            'gender' => 'encrypted',
            'occupation' => 'encrypted',
            'emergency_contact_name' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
            'status' => TenantStatus::class,
        ];
    }

    public function workspace() { return $this->belongsTo(Workspace::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
}
