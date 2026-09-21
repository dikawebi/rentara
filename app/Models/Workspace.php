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

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
