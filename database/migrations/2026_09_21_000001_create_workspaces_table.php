<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'manager', 'staff']);
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'status', 'workspace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
