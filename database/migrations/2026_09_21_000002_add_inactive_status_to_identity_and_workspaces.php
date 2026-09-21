<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->change();
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('status', 'inactive')->update(['status' => 'suspended']);
        DB::table('workspaces')->where('status', 'inactive')->update(['status' => 'suspended']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended'])->default('active')->change();
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended'])->default('active')->change();
        });
    }
};
