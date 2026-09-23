<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException("Unsupported database driver '{$driver}'; audit release support is SQLite/MySQL/MariaDB only.");
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqlite(false);
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['workspace_id']);
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException("Unsupported database driver '{$driver}'; audit release support is SQLite/MySQL/MariaDB only.");
        }

        $orphans = DB::table('audit_logs')
            ->where(function ($query): void {
                $query->whereNotNull('user_id')->whereNotExists(function ($subquery): void {
                    $subquery->selectRaw('1')->from('users')->whereColumn('users.id', 'audit_logs.user_id');
                });
            })
            ->orWhere(function ($query): void {
                $query->whereNotNull('workspace_id')->whereNotExists(function ($subquery): void {
                    $subquery->selectRaw('1')->from('workspaces')->whereColumn('workspaces.id', 'audit_logs.workspace_id');
                });
            })->exists();

        if ($orphans) {
            throw new RuntimeException('Cannot restore audit log foreign keys: orphan historical user_id/workspace_id values exist; no data was changed.');
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqlite(true);
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });
    }

    private function rebuildSqlite(bool $withForeignKeys): void
    {
        $foreignKeys = (bool) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
        $indexes = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'audit_logs' AND sql IS NOT NULL");
        $triggers = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = 'audit_logs'");

        DB::statement('PRAGMA foreign_keys=OFF');
        try {
            foreach ($triggers as $trigger) DB::statement('DROP TRIGGER IF EXISTS "'.str_replace('"', '""', $trigger->name).'"');
            foreach ($indexes as $index) DB::statement('DROP INDEX IF EXISTS "'.str_replace('"', '""', $index->name).'"');
            Schema::rename('audit_logs', 'audit_logs_old');

            Schema::create('audit_logs', function (Blueprint $table) use ($withForeignKeys): void {
                $table->id();
                if ($withForeignKeys) {
                    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                    $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->unsignedBigInteger('workspace_id')->nullable();
                }
                $table->string('event');
                $table->nullableMorphs('auditable');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
            DB::statement('INSERT INTO audit_logs SELECT * FROM audit_logs_old');
            Schema::drop('audit_logs_old');
            foreach ($indexes as $index) {
                DB::statement(preg_replace('/^CREATE INDEX /i', 'CREATE INDEX IF NOT EXISTS ', $index->sql));
            }
            foreach ($triggers as $trigger) DB::statement($trigger->sql);
        } finally {
            DB::statement('PRAGMA foreign_keys='.($foreignKeys ? 'ON' : 'OFF'));
        }
    }
};
