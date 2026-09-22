<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table): void {
            $table->dropForeign(['workspace_id', 'property_id']);
            $table->foreign(['workspace_id', 'property_id'])
                ->references(['workspace_id', 'id'])->on('properties')->restrictOnDelete();
            if (! Schema::hasColumn('rental_contracts', 'termination_reason')) {
                $table->string('termination_reason', 1000)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table): void {
            $table->dropForeign(['workspace_id', 'property_id']);
            $table->foreign(['workspace_id', 'property_id'])
                ->references(['workspace_id', 'id'])->on('properties')->cascadeOnDelete();
            $table->dropColumn('termination_reason');
        });
    }
};
