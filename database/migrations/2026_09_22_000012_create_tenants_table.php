<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('identity_number')->nullable();
            $table->text('date_of_birth')->nullable();
            $table->text('gender')->nullable();
            $table->text('occupation')->nullable();
            $table->text('emergency_contact_name')->nullable();
            $table->text('emergency_contact_phone')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            // unit_id may be cleared when a unit is deleted, while workspace_id
            // must remain non-null so the tenant stays scoped to its workspace.
            // Workspace ownership is enforced by the application when assigning a
            // unit. Keep the database relationships independent so deleting a unit
            // can clear this nullable reference without blocking the delete.
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'unit_id']);
            $table->index(['workspace_id', 'name']);
            $table->index(['workspace_id', 'phone']);
            $table->index(['workspace_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
