<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new \RuntimeException("Unsupported database driver '{$driver}'; media migrations support SQLite/MySQL/MariaDB only.");
        }
        if ($driver === 'sqlite') {
            $this->rebuildSqliteMedia(true);
        } else {
            Schema::table('media', function (Blueprint $t) { $t->unsignedBigInteger('maintenance_ticket_id')->nullable()->after('unit_id'); $t->index(['workspace_id', 'maintenance_ticket_id']); $t->foreign(['workspace_id', 'maintenance_ticket_id'])->references(['workspace_id', 'id'])->on('maintenance_tickets')->cascadeOnDelete(); });
            $this->dropXorConstraint();
            DB::statement('ALTER TABLE media ADD CONSTRAINT media_parent_xor CHECK ((property_id IS NOT NULL AND unit_id IS NULL AND maintenance_ticket_id IS NULL) OR (property_id IS NULL AND unit_id IS NOT NULL AND maintenance_ticket_id IS NULL) OR (property_id IS NULL AND unit_id IS NULL AND maintenance_ticket_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new \RuntimeException("Unsupported database driver '{$driver}'; media migrations support SQLite/MySQL/MariaDB only.");
        }
        if (Schema::hasColumn('media', 'maintenance_ticket_id') && DB::table('media')->whereNotNull('maintenance_ticket_id')->exists()) {
            throw new \RuntimeException('Cannot roll back maintenance media schema while ticket media exists; archive or migrate those rows with a compatible schema first.');
        }
        if ($driver === 'sqlite') {
             $this->rebuildSqliteMedia(false);
             return;
        }
        Schema::table('media', function (Blueprint $t) { $t->dropConstrainedForeignId('maintenance_ticket_id'); $t->dropIndex(['workspace_id', 'maintenance_ticket_id']); });
        $this->dropXorConstraint();
        DB::statement('ALTER TABLE media ADD CONSTRAINT media_parent_xor CHECK ((property_id IS NOT NULL AND unit_id IS NULL) OR (property_id IS NULL AND unit_id IS NOT NULL))');
    }

    private function dropXorConstraint(): void
    {
        if (DB::connection()->getDriverName() === 'mysql' && str_contains(strtolower((string) DB::selectOne('select version() as version')->version), 'mariadb')) {
            DB::statement('ALTER TABLE media DROP CONSTRAINT media_parent_xor');
        } elseif (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE media DROP CHECK media_parent_xor');
        } else {
            DB::statement('ALTER TABLE media DROP CONSTRAINT media_parent_xor');
        }
    }

    private function rebuildSqliteMedia(bool $withTicket): void
    {
        $foreignKeys = (bool) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
        $oldTable = $withTicket ? 'media' : 'media';
        $temporaryTable = $withTicket ? 'media_old' : 'media_with_ticket';
        $count = (int) DB::table($oldTable)->count();

        DB::statement('PRAGMA foreign_keys=OFF');
        try {
            DB::statement('DROP TRIGGER IF EXISTS media_parent_xor_insert');
            DB::statement('DROP TRIGGER IF EXISTS media_parent_xor_update');
            DB::statement('DROP INDEX IF EXISTS media_workspace_id_property_id_sort_order_index');
            DB::statement('DROP INDEX IF EXISTS media_workspace_id_unit_id_sort_order_index');
            DB::statement('DROP INDEX IF EXISTS media_workspace_id_maintenance_ticket_id_index');
            Schema::rename($oldTable, $temporaryTable);
            Schema::create('media', function (Blueprint $t) use ($withTicket) {
                $t->id(); $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $t->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
                $t->foreignId('unit_id')->nullable()->constrained('units')->cascadeOnDelete();
                if ($withTicket) $t->unsignedBigInteger('maintenance_ticket_id')->nullable();
                $t->string('disk')->default('local'); $t->string('path'); $t->string('original_name'); $t->string('mime_type'); $t->string('extension', 10); $t->unsignedBigInteger('size_bytes'); $t->string('checksum', 64)->nullable(); $t->string('caption')->nullable(); $t->unsignedSmallInteger('sort_order')->default(0); $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
                $t->index(['workspace_id', 'property_id', 'sort_order']); $t->index(['workspace_id', 'unit_id', 'sort_order']);
                if ($withTicket) $t->index(['workspace_id', 'maintenance_ticket_id']);
                $t->foreign(['workspace_id', 'property_id'])->references(['workspace_id', 'id'])->on('properties')->cascadeOnDelete();
                $t->foreign(['workspace_id', 'unit_id'])->references(['workspace_id', 'id'])->on('units')->cascadeOnDelete();
                if ($withTicket) $t->foreign(['workspace_id', 'maintenance_ticket_id'])->references(['workspace_id', 'id'])->on('maintenance_tickets')->cascadeOnDelete();
            });
            $commonColumns = 'id,workspace_id,property_id,unit_id,disk,path,original_name,mime_type,extension,size_bytes,checksum,caption,sort_order,uploaded_by,created_at,updated_at';
            $targetColumns = $withTicket ? 'id,workspace_id,property_id,unit_id,maintenance_ticket_id,disk,path,original_name,mime_type,extension,size_bytes,checksum,caption,sort_order,uploaded_by,created_at,updated_at' : $commonColumns;
            $sourceHasTicket = Schema::hasColumn($temporaryTable, 'maintenance_ticket_id');
            $sourceSelect = $withTicket
                ? 'id,workspace_id,property_id,unit_id,'.($sourceHasTicket ? 'maintenance_ticket_id' : 'NULL').',disk,path,original_name,mime_type,extension,size_bytes,checksum,caption,sort_order,uploaded_by,created_at,updated_at'
                : $commonColumns;
            DB::statement("INSERT INTO media ({$targetColumns}) SELECT {$sourceSelect} FROM {$temporaryTable}");
            Schema::drop($temporaryTable);
            $condition = $withTicket
                ? '((NEW.property_id IS NOT NULL AND NEW.unit_id IS NULL AND NEW.maintenance_ticket_id IS NULL) OR (NEW.property_id IS NULL AND NEW.unit_id IS NOT NULL AND NEW.maintenance_ticket_id IS NULL) OR (NEW.property_id IS NULL AND NEW.unit_id IS NULL AND NEW.maintenance_ticket_id IS NOT NULL))'
                : '((NEW.property_id IS NOT NULL AND NEW.unit_id IS NULL) OR (NEW.property_id IS NULL AND NEW.unit_id IS NOT NULL))';
            DB::statement("CREATE TRIGGER media_parent_xor_insert BEFORE INSERT ON media WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, 'media parent xor violation'); END");
            DB::statement("CREATE TRIGGER media_parent_xor_update BEFORE UPDATE OF property_id,unit_id".($withTicket ? ',maintenance_ticket_id' : '')." ON media WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, 'media parent xor violation'); END");
            if ((int) DB::table('media')->count() !== $count || DB::selectOne('SELECT count(*) AS count FROM pragma_foreign_key_check')->count !== 0) throw new \RuntimeException('SQLite media rebuild validation failed.');
        } finally {
            DB::statement('PRAGMA foreign_keys='.($foreignKeys ? 'ON' : 'OFF'));
        }
    }
};
