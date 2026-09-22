<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'name',
        'property_type',
        'address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'phone',
        'email',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'status' => PropertyStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function assignments()
    {
        return $this->hasMany(PropertyAssignment::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'property_assignments')->withTimestamps();
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }
}
