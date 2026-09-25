<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rental_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete();
            $table->json('applicant_snapshot');
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('requested_move_in');
            $table->unsignedTinyInteger('requested_duration_months');
            $table->text('applicant_note')->nullable();
            $table->string('privacy_notice_version', 50);
            $table->timestamp('privacy_accepted_at');
            $table->string('status', 30)->default('submitted');
            $table->string('active_application_key', 128)->nullable()->unique();
            $table->json('listing_snapshot');
            $table->timestamps();

            $table->index(['applicant_id', 'status']);
            $table->index(['listing_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_applications');
    }
};
