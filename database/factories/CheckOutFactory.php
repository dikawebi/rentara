<?php
namespace Database\Factories; use App\Models\CheckOut; use Illuminate\Database\Eloquent\Factories\Factory;
class CheckOutFactory extends Factory {protected $model=CheckOut::class;public function definition():array{return ['rental_contract_id'=>null,'checked_out_at'=>now(),'unpaid_amount'=>0,'damage_amount'=>0,'deposit_returned'=>0,'deposit_deduction'=>0];}}
