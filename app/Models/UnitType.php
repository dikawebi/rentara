<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['workspace_id', 'name', 'description', 'default_capacity'];

    protected function casts(): array
    {
        return ['default_capacity' => 'integer'];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }
}
