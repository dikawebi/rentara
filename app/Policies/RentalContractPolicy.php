<?php
namespace App\Policies;
use App\Enums\{PlatformRole,WorkspaceMemberRole,UserStatus}; use App\Models\{RentalContract,User,Workspace,Property};
class RentalContractPolicy {
 private function role(User $u, Workspace $w): ?WorkspaceMemberRole { if($u->platform_role===PlatformRole::SuperAdmin)return null; return $w->members()->where('user_id',$u->id)->where('status',UserStatus::Active->value)->first()?->role; }
 private function allowed(User $u, RentalContract $c, bool $manage=false): bool { $w=$c->workspace; $r=$this->role($u,$w); if($r===WorkspaceMemberRole::Owner)return true; if(!in_array($r,[WorkspaceMemberRole::Manager,WorkspaceMemberRole::Staff],true))return false; if(! $c->property->assignments()->where('user_id',$u->id)->exists())return false; return !$manage || $r===WorkspaceMemberRole::Manager; }
 public function viewAny(User $u, Workspace $w):bool{return $this->role($u,$w)!==null;}
 public function view(User $u,RentalContract $c):bool{return $this->allowed($u,$c);}
 public function create(User $u, Workspace $w):bool{$r=$this->role($u,$w);return in_array($r,[WorkspaceMemberRole::Owner,WorkspaceMemberRole::Manager],true);}
 public function update(User $u,RentalContract $c):bool{return $this->allowed($u,$c,true);}
 public function delete(User $u,RentalContract $c):bool{return $this->allowed($u,$c,true);}
 public function restore(User $u,RentalContract $c):bool{return $this->allowed($u,$c,true);}
 public function forceDelete():bool{return false;}
 public function operate(User $u,RentalContract $c):bool{return $this->allowed($u,$c,true) || $this->allowed($u,$c,false);}
}
