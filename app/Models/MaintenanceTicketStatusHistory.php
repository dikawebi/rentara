<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use RuntimeException;
class MaintenanceTicketStatusHistory extends \Illuminate\Database\Eloquent\Model {
 use HasFactory; public const UPDATED_AT=null; protected $fillable=['maintenance_ticket_id','from_status','to_status','changed_by','reason'];
 protected static function booted(): void { static::updating(fn()=>throw new RuntimeException('Status history is append-only.')); static::deleting(fn()=>throw new RuntimeException('Status history is append-only.')); }
 protected function casts(): array{return ['from_status'=>\App\Enums\MaintenanceTicketStatus::class,'to_status'=>\App\Enums\MaintenanceTicketStatus::class];}
 public function ticket(){return $this->belongsTo(MaintenanceTicket::class,'maintenance_ticket_id');} public function actor(){return $this->belongsTo(User::class,'changed_by');}
}
