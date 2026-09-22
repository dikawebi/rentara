<?php
namespace Database\Factories; use App\Models\CheckIn; use Illuminate\Database\Eloquent\Factories\Factory;
class CheckInFactory extends Factory {protected $model=CheckIn::class;public function definition():array{return ['rental_contract_id'=>null,'checked_in_at'=>now(),'deposit_received'=>0];}}
