<?php

namespace App\Models;

use App\Enums\WorkspaceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['owner_id', 'name', 'slug', 'status', 'timezone', 'currency'];

    protected function casts(): array
    {
        return ['status' => WorkspaceStatus::class];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }

    public function floors()
    {
        return $this->hasMany(Floor::class);
    }

    public function blocks()
    {
        return $this->hasMany(Block::class);
    }

    public function unitTypes()
    {
        return $this->hasMany(UnitType::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function propertyAssignments()
    {
        return $this->hasMany(PropertyAssignment::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function amenities()
    {
        return $this->hasMany(Amenity::class);
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }
}
