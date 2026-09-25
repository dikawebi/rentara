<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('requested_move_in');
            $table->date('start_date');
            $table->date('end_date');
            $table->json('terms_snapshot');
            $table->string('status', 20)->default('verified');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('payment_amount')->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->text('payment_note')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'status', 'start_date', 'end_date']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
