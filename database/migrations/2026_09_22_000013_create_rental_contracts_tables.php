<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
   Schema::table('tenants', function(Blueprint $t){ $t->unique(['workspace_id','id'], 'tenants_workspace_id_id_unique'); });
  Schema::create('rental_contracts', function(Blueprint $t){
    $t->id(); $t->foreignId('workspace_id')->constrained()->restrictOnDelete(); $t->foreignId('property_id')->constrained()->restrictOnDelete(); $t->foreignId('unit_id')->constrained()->restrictOnDelete();
    $t->string('contract_number'); $t->enum('status',['draft','pending','active','expiring','completed','terminated','cancelled'])->default('draft');
    $t->date('start_date'); $t->date('end_date'); $t->unsignedBigInteger('rental_price')->default(0); $t->unsignedBigInteger('deposit_amount')->default(0); $t->text('notes')->nullable(); $t->string('termination_reason', 1000)->nullable(); $t->timestamps(); $t->softDeletes();
   $t->unique(['workspace_id','contract_number']); $t->unique(['workspace_id','id']); $t->index(['workspace_id','unit_id','status']);
    $t->foreign(['workspace_id','property_id'])->references(['workspace_id','id'])->on('properties')->restrictOnDelete();
    // Contracts are historical records. A unit cannot remove them, including
    // completed, terminated, or renewed contracts.
    $t->foreign(['workspace_id','unit_id'])->references(['workspace_id','id'])->on('units')->restrictOnDelete();
  });
   Schema::create('contract_tenant', function(Blueprint $t){$t->id();$t->foreignId('workspace_id')->constrained()->restrictOnDelete();$t->foreignId('rental_contract_id')->constrained()->restrictOnDelete();$t->foreignId('tenant_id')->constrained()->restrictOnDelete();$t->timestamps();$t->unique(['rental_contract_id','tenant_id']);$t->unique(['workspace_id','rental_contract_id','tenant_id']);$t->foreign(['workspace_id','rental_contract_id'])->references(['workspace_id','id'])->on('rental_contracts')->restrictOnDelete();$t->foreign(['workspace_id','tenant_id'])->references(['workspace_id','id'])->on('tenants')->restrictOnDelete();});
   Schema::create('check_ins', function(Blueprint $t){$t->id();$t->foreignId('rental_contract_id')->unique()->constrained()->restrictOnDelete();$t->dateTime('checked_in_at');$t->unsignedBigInteger('deposit_received')->default(0);$t->text('notes')->nullable();$t->timestamps();});
   Schema::create('check_outs', function(Blueprint $t){$t->id();$t->foreignId('rental_contract_id')->unique()->constrained()->restrictOnDelete();$t->dateTime('checked_out_at');$t->unsignedBigInteger('unpaid_amount')->default(0);$t->unsignedBigInteger('damage_amount')->default(0);$t->unsignedBigInteger('deposit_returned')->default(0);$t->unsignedBigInteger('deposit_deduction')->default(0);$t->string('reason')->nullable();$t->text('notes')->nullable();$t->timestamps();});
 }
 public function down(): void { Schema::dropIfExists('check_outs');Schema::dropIfExists('check_ins');Schema::dropIfExists('contract_tenant');Schema::dropIfExists('rental_contracts'); Schema::table('tenants', function(Blueprint $t){ $t->dropUnique('tenants_workspace_id_id_unique'); }); }
};
