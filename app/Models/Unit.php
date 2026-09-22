<?php

namespace App\Models;

use App\Enums\RentalPeriod;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'property_id',
        'building_id',
        'floor_id',
        'block_id',
        'unit_type_id',
        'unit_number',
        'name',
        'area',
        'rental_price',
        'rental_period',
        'capacity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'rental_price' => 'integer',
            'rental_period' => RentalPeriod::class,
            'capacity' => 'integer',
            'status' => UnitStatus::class,
        ];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function unitType()
    {
        return $this->belongsTo(UnitType::class);
    }
}
