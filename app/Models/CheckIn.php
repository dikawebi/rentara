<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class CheckIn extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $fillable=['rental_contract_id','checked_in_at','deposit_received','notes'];
    protected function casts(): array{return ['checked_in_at'=>'datetime','deposit_received'=>'integer'];}
    public function contract(){return $this->belongsTo(RentalContract::class,'rental_contract_id');}
}
