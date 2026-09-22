<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('default_capacity')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['workspace_id', 'name']);
            $table->index(['workspace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_types');
    }
};
