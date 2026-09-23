<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        Schema::create('maintenance_ticket_status_mutation_guards', function (Blueprint $table) {
            $table->unsignedBigInteger('maintenance_ticket_id')->primary();
            $table->foreign('maintenance_ticket_id', 'mt_status_guard_ticket_fk')->references('id')->on('maintenance_tickets')->cascadeOnDelete();
        });
        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER maintenance_ticket_status_no_raw_update BEFORE UPDATE OF status ON maintenance_tickets WHEN NOT EXISTS (SELECT 1 FROM maintenance_ticket_status_mutation_guards WHERE maintenance_ticket_id = OLD.id) BEGIN SELECT RAISE(ABORT, 'ticket status must use a transition'); END");
            DB::statement("CREATE TRIGGER maintenance_history_no_update BEFORE UPDATE ON maintenance_ticket_status_histories BEGIN SELECT RAISE(ABORT, 'status history is append-only'); END");
            DB::statement("CREATE TRIGGER maintenance_history_no_delete BEFORE DELETE ON maintenance_ticket_status_histories BEGIN SELECT RAISE(ABORT, 'status history is append-only'); END");
        } elseif (self::supportsMysqlTriggers($driver)) {
            DB::unprepared("CREATE TRIGGER maintenance_ticket_status_no_raw_update BEFORE UPDATE ON maintenance_tickets FOR EACH ROW BEGIN IF NOT (OLD.status <=> NEW.status) AND COALESCE(@rentara_maintenance_ticket_status_id, 0) <> OLD.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ticket status must use a transition'; END IF; END");
            DB::unprepared("CREATE TRIGGER maintenance_history_no_update BEFORE UPDATE ON maintenance_ticket_status_histories FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'status history is append-only'");
            DB::unprepared("CREATE TRIGGER maintenance_history_no_delete BEFORE DELETE ON maintenance_ticket_status_histories FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'status history is append-only'");
        } elseif ($driver === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION reject_raw_maintenance_ticket_status_update() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF OLD.status IS DISTINCT FROM NEW.status AND COALESCE(current_setting('rentara.maintenance_ticket_status_id', true), '') <> OLD.id::text THEN RAISE EXCEPTION 'ticket status must use a transition'; END IF; RETURN NEW; END; $$");
            DB::unprepared("CREATE TRIGGER maintenance_ticket_status_no_raw_update BEFORE UPDATE ON maintenance_tickets FOR EACH ROW EXECUTE FUNCTION reject_raw_maintenance_ticket_status_update()");
            DB::unprepared("CREATE OR REPLACE FUNCTION reject_maintenance_history_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'status history is append-only'; END; $$");
            DB::unprepared("CREATE TRIGGER maintenance_history_no_update BEFORE UPDATE ON maintenance_ticket_status_histories FOR EACH ROW EXECUTE FUNCTION reject_maintenance_history_mutation()");
            DB::unprepared("CREATE TRIGGER maintenance_history_no_delete BEFORE DELETE ON maintenance_ticket_status_histories FOR EACH ROW EXECUTE FUNCTION reject_maintenance_history_mutation()");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS maintenance_ticket_status_no_raw_update');
            DB::statement('DROP TRIGGER IF EXISTS maintenance_history_no_update');
            DB::statement('DROP TRIGGER IF EXISTS maintenance_history_no_delete');
        } elseif (self::supportsMysqlTriggers($driver)) {
            DB::statement('DROP TRIGGER IF EXISTS maintenance_ticket_status_no_raw_update');
            DB::statement('DROP TRIGGER IF EXISTS maintenance_history_no_update');
            DB::statement('DROP TRIGGER IF EXISTS maintenance_history_no_delete');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS maintenance_ticket_status_no_raw_update ON maintenance_tickets');
            DB::statement('DROP FUNCTION IF EXISTS reject_raw_maintenance_ticket_status_update()');
            DB::statement('DROP TRIGGER IF EXISTS maintenance_history_no_update ON maintenance_ticket_status_histories');
            DB::statement('DROP TRIGGER IF EXISTS maintenance_history_no_delete ON maintenance_ticket_status_histories');
            DB::statement('DROP FUNCTION IF EXISTS reject_maintenance_history_mutation()');
        }
        Schema::dropIfExists('maintenance_ticket_status_mutation_guards');
    }

    public static function supportsMysqlTriggers(string $driver): bool
    {
        return in_array($driver, ['mysql', 'mariadb'], true);
    }
};
