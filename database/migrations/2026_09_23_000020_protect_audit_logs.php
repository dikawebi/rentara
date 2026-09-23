<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // The audit FKs are nullable for historical compatibility, but their
            // ON DELETE SET NULL action is intentionally blocked here. Parent
            // deletion is therefore restricted and must be handled separately;
            // no UPDATE path may weaken append-only guarantees.
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit logs are append-only'); END");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit logs are append-only'); END");
            return;
        }

        // MySQL 8.0.16+ and MariaDB 10.6+ both support SIGNAL in triggers.
        if (self::supportsMysqlTriggers($driver)) {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit logs are append-only'");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit logs are append-only'");
            return;
        }

        throw new \RuntimeException("Unsupported database driver '{$driver}' for audit log protection.");
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite' || self::supportsMysqlTriggers($driver)) {
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_delete');
            return;
        }

        throw new \RuntimeException("Unsupported database driver '{$driver}' for audit log protection.");
    }

    public static function supportsMysqlTriggers(string $driver): bool
    {
        return in_array($driver, ['mysql', 'mariadb'], true);
    }
};
