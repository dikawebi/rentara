<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['buildings', 'floors', 'blocks'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['property_id', 'name']);
                $table->index(['workspace_id', 'property_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('buildings');
    }
};
