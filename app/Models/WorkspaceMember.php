<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkspaceMember extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'user_id', 'role', 'status', 'joined_at'];

    protected function casts(): array
    {
        return ['role' => WorkspaceMemberRole::class, 'status' => UserStatus::class, 'joined_at' => 'datetime'];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isCanonicalOwner(): bool
    {
        return $this->workspace->owner_id === $this->user_id;
    }
}
