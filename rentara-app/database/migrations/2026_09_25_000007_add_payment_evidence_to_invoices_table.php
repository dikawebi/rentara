<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('payment_evidence_storage_path')->nullable();
            $table->string('payment_evidence_mime_type', 100)->nullable();
            $table->unsignedBigInteger('payment_evidence_byte_size')->nullable();
            $table->string('payment_evidence_original_name')->nullable();
            $table->foreignId('payment_evidence_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_evidence_uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['payment_evidence_uploaded_by']);
            $table->dropColumn([
                'payment_evidence_storage_path',
                'payment_evidence_mime_type',
                'payment_evidence_byte_size',
                'payment_evidence_original_name',
                'payment_evidence_uploaded_by',
                'payment_evidence_uploaded_at',
            ]);
        });
    }
};
