<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('booking_expiry_days')->default(7);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->unsignedTinyInteger('booking_expiry_days')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('booking_expiry_days')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->text('expiry_reason')->nullable();
            $table->foreignId('expired_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['expired_by']);
            $table->dropColumn(['booking_expiry_days', 'expired_at', 'expiry_reason', 'expired_by']);
        });
        Schema::table('properties', fn (Blueprint $table): mixed => $table->dropColumn('booking_expiry_days'));
        Schema::table('organizations', fn (Blueprint $table): mixed => $table->dropColumn('booking_expiry_days'));
    }
};
