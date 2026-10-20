<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        if (Schema::hasTable('branch_worker') && Schema::hasTable('pond_worker')) {
            foreach (DB::table('branch_worker')->get() as $row) {
                $pondIds = DB::table('ponds')->where('branch_id', $row->branch_id)->pluck('id');
                foreach ($pondIds as $pondId) {
                    DB::table('pond_worker')->insertOrIgnore([
                        'pond_id' => $pondId,
                        'user_id' => $row->user_id,
                        'position' => $row->position,
                        'hired_at' => $row->hired_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            }
        }

        if (Schema::hasColumn('warehouse_items', 'branch_id') && ! Schema::hasColumn('warehouse_items', 'pond_id')) {
            Schema::table('warehouse_items', function (Blueprint $table) {
                $table->foreignId('pond_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });

            foreach (DB::table('warehouse_items')->orderBy('id')->get() as $item) {
                $pondId = DB::table('ponds')->where('branch_id', $item->branch_id)->orderBy('id')->value('id');
                if ($pondId) {
                    DB::table('warehouse_items')->where('id', $item->id)->update(['pond_id' => $pondId]);
                }
            }

            Schema::table('warehouse_items', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropUnique(['branch_id', 'category', 'name']);
                $table->dropColumn('branch_id');
                $table->unique(['pond_id', 'category', 'name']);
            });
        }

        if (Schema::hasColumn('feed_types', 'branch_id') && ! Schema::hasColumn('feed_types', 'pond_id')) {
            Schema::table('feed_types', function (Blueprint $table) {
                $table->foreignId('pond_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });

            foreach (DB::table('feed_types')->orderBy('id')->get() as $feedType) {
                $pondId = DB::table('ponds')->where('branch_id', $feedType->branch_id)->orderBy('id')->value('id');
                if ($pondId) {
                    DB::table('feed_types')->where('id', $feedType->id)->update(['pond_id' => $pondId]);
                }
            }

            Schema::table('feed_types', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                if ($this->indexExists('feed_types', 'feed_types_branch_id_name_unique')) {
                    $table->dropUnique(['branch_id', 'name']);
                }
                $table->dropColumn('branch_id');
                $table->unique(['pond_id', 'name']);
            });
        }

        if (Schema::hasColumn('expenses', 'branch_id') && ! Schema::hasColumn('expenses', 'pond_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('pond_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });

            foreach (DB::table('expenses')->orderBy('id')->get() as $expense) {
                $pondId = DB::table('ponds')->where('branch_id', $expense->branch_id)->orderBy('id')->value('id');
                if ($pondId) {
                    DB::table('expenses')->where('id', $expense->id)->update(['pond_id' => $pondId]);
                }
            }

            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasColumn('ponds', 'branch_id')) {
            Schema::table('ponds', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        Schema::dropIfExists('branch_worker');
        Schema::dropIfExists('branches');
    }

    public function down(): void
    {
        // Branch architecture intentionally removed.
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return (int) ($result[0]->aggregate ?? 0) > 0;
    }
};
