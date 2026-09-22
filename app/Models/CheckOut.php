<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class CheckOut extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $fillable=['rental_contract_id','checked_out_at','unpaid_amount','damage_amount','deposit_returned','deposit_deduction','reason','notes'];
    protected function casts(): array{return ['checked_out_at'=>'datetime','unpaid_amount'=>'integer','damage_amount'=>'integer','deposit_returned'=>'integer','deposit_deduction'=>'integer'];}
    public function contract(){return $this->belongsTo(RentalContract::class,'rental_contract_id');}
}
