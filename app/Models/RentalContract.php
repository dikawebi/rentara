<?php
namespace App\Models;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalContract extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['workspace_id','property_id','unit_id','contract_number','status','start_date','end_date','rental_price','deposit_amount','notes','termination_reason'];
    protected function casts(): array { return ['status'=>ContractStatus::class,'start_date'=>'date','end_date'=>'date','rental_price'=>'integer','deposit_amount'=>'integer']; }
    public function workspace(){return $this->belongsTo(Workspace::class);}
    public function property(){return $this->belongsTo(Property::class);}
    public function unit(){return $this->belongsTo(Unit::class);}
    public function tenants(){return $this->belongsToMany(Tenant::class,'contract_tenant')->withTimestamps();}
    public function checkIn(){return $this->hasOne(CheckIn::class);}
    public function checkOut(){return $this->hasOne(CheckOut::class);}
    public function invoices(){return $this->hasMany(Invoice::class, 'contract_id');}
}
