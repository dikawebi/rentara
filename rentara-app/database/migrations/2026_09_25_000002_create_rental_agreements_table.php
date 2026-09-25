<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('terms_snapshot');
            $table->string('contract_storage_path');
            $table->string('contract_original_name');
            $table->string('contract_mime_type', 100);
            $table->unsignedBigInteger('contract_byte_size');
            $table->foreignId('organization_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('organization_approved_at')->nullable();
            $table->foreignId('applicant_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applicant_approved_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_agreements');
    }
};
