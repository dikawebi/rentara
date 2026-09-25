<?php

namespace App\Models;

use App\ComplaintCategory;
use App\ComplaintPriority;
use App\ComplaintStatus;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use HasFactory;

    protected $fillable = ['tenancy_id', 'tenant_id', 'organization_id', 'unit_id', 'category', 'title', 'description', 'priority', 'status', 'assigned_to', 'resolution_notes'];

    protected function casts(): array
    {
        return ['category' => ComplaintCategory::class, 'priority' => ComplaintPriority::class, 'status' => ComplaintStatus::class];
    }

    /** @return BelongsTo<Tenancy, $this> */
    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<ComplaintComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ComplaintComment::class);
    }

    /** @return HasMany<ComplaintAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class);
    }
}
