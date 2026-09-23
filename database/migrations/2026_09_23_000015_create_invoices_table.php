<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained('rental_contracts')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 100);
            $table->date('period_start'); $table->date('period_end'); $table->date('due_date');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->dateTime('paid_at')->nullable();
            $table->enum('payment_method', ['cash', 'bank_transfer', 'other'])->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->text('payment_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->softDeletes();
            $table->unique(['workspace_id', 'invoice_number']);
            $table->unique(['workspace_id', 'contract_id', 'period_start', 'period_end']);
            $table->unique(['workspace_id', 'id']);
            $table->foreign(['workspace_id', 'property_id'])->references(['workspace_id', 'id'])->on('properties')->restrictOnDelete();
            $table->foreign(['workspace_id', 'contract_id'])->references(['workspace_id', 'id'])->on('rental_contracts')->restrictOnDelete();
            $table->foreign(['workspace_id', 'tenant_id'])->references(['workspace_id', 'id'])->on('tenants')->restrictOnDelete();
            $table->index(['workspace_id', 'property_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('invoices'); }
};
