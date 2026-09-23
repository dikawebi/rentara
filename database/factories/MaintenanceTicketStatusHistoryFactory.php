<?php
namespace Database\Factories;
use App\Models\{MaintenanceTicket,MaintenanceTicketStatusHistory}; use App\Enums\MaintenanceTicketStatus; use Illuminate\Database\Eloquent\Factories\Factory;
class MaintenanceTicketStatusHistoryFactory extends Factory {protected $model=MaintenanceTicketStatusHistory::class; public function definition():array{return ['maintenance_ticket_id'=>MaintenanceTicket::factory(),'from_status'=>null,'to_status'=>MaintenanceTicketStatus::Submitted,'changed_by'=>null];}}
