<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->nullable();
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['workspace_id', 'property_id'])->references(['workspace_id', 'id'])->on('properties')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'unit_id'])->references(['workspace_id', 'id'])->on('units')->cascadeOnDelete();

            $table->index(['workspace_id', 'property_id', 'sort_order']);
            $table->index(['workspace_id', 'unit_id', 'sort_order']);
        });

        DB::statement('ALTER TABLE media ADD CONSTRAINT media_parent_xor CHECK ((property_id IS NOT NULL AND unit_id IS NULL) OR (property_id IS NULL AND unit_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
