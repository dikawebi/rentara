<?php
namespace App\Models;
use App\Enums\{MaintenanceTicketChargeTo,MaintenanceTicketPriority,MaintenanceTicketStatus};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
class MaintenanceTicket extends \Illuminate\Database\Eloquent\Model {
 use HasFactory,SoftDeletes;
 private static bool $allowStatusMutation = false;
 public static function allowStatusMutation(bool $allowed): void { self::$allowStatusMutation = $allowed; }
 protected static function booted(): void {
  static::updating(function (self $ticket): void {
    if ($ticket->isDirty('status') && ! self::$allowStatusMutation) {
     throw ValidationException::withMessages(['status' => 'Perubahan status harus melalui transition().']);
    }
    if (in_array($ticket->getOriginal('status'), [MaintenanceTicketStatus::Closed->value, MaintenanceTicketStatus::Rejected->value], true) && collect($ticket->getDirty())->except(['deleted_at'])->isNotEmpty()) {
    throw ValidationException::withMessages(['ticket' => 'Tiket terminal tidak dapat diubah.']);
   }
  });
 }
 protected $fillable=['workspace_id','property_id','unit_id','tenant_id','assigned_to','submitted_by','status','priority','title','description','estimated_cost','actual_cost','charged_to','rejection_reason','waiting_reason','resolved_at'];
 protected function casts(): array { return ['status'=>MaintenanceTicketStatus::class,'priority'=>MaintenanceTicketPriority::class,'charged_to'=>MaintenanceTicketChargeTo::class,'estimated_cost'=>'integer','actual_cost'=>'integer','resolved_at'=>'datetime']; }
 public function workspace(){return $this->belongsTo(Workspace::class);} public function property(){return $this->belongsTo(Property::class);} public function unit(){return $this->belongsTo(Unit::class);} public function tenant(){return $this->belongsTo(Tenant::class);} public function assignee(){return $this->belongsTo(User::class,'assigned_to');} public function submitter(){return $this->belongsTo(User::class,'submitted_by');} public function history(){return $this->hasMany(MaintenanceTicketStatusHistory::class)->orderBy('id');} public function media(){return $this->hasMany(Media::class);}
}
