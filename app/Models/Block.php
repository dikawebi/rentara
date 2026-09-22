<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Block extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['workspace_id', 'property_id', 'name', 'sort_order', 'notes'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }
}
