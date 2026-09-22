<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenity_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained('amenities')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['workspace_id', 'amenity_id'])
                ->references(['workspace_id', 'id'])
                ->on('amenities')
                ->cascadeOnDelete();
            $table->foreign(['workspace_id', 'unit_id'])
                ->references(['workspace_id', 'id'])
                ->on('units')
                ->cascadeOnDelete();
            $table->unique(['amenity_id', 'unit_id']);
            $table->index(['workspace_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_unit');
    }
};
