<?php
namespace Database\Factories;
use App\Enums\ContractStatus; use App\Models\{RentalContract,Workspace,Property,Unit}; use Illuminate\Database\Eloquent\Factories\Factory;
class RentalContractFactory extends Factory { protected $model=RentalContract::class; public function definition():array{return ['workspace_id'=>Workspace::factory(),'property_id'=>Property::factory(),'unit_id'=>Unit::factory(),'contract_number'=>fake()->unique()->bothify('CTR-######'),'status'=>ContractStatus::Draft,'start_date'=>now()->toDateString(),'end_date'=>now()->addYear()->toDateString(),'rental_price'=>1000000,'deposit_amount'=>0];} }
