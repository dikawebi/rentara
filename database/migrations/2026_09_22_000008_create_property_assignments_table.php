<?php

use App\Support\BackfillPropertyAssignments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'user_id']);
            $table->index(['workspace_id', 'user_id']);
            $table->index(['property_id']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('property_assignments');
    }

    /**
     * Idempotent backfill: active manager|staff x non-trashed properties,
     * skipping suspended memberships and never creating owner rows.
     */
    private function backfill(): void
    {
        (new BackfillPropertyAssignments)->run();
    }
};
