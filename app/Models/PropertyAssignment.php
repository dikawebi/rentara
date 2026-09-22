<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'property_id', 'user_id', 'created_by'];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
