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
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('region');
                $table->foreignId('director_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('ponds', 'branch_id')) {
            Schema::table('ponds', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        foreach (DB::table('ponds')->whereNull('branch_id')->orderBy('id')->get() as $pond) {
            $branchId = DB::table('branches')->insertGetId([
                'name' => $pond->name,
                'region' => $pond->region,
                'director_id' => $pond->director_id,
                'created_by' => $pond->created_by,
                'status' => $pond->status,
                'notes' => $pond->notes,
                'created_at' => $pond->created_at,
                'updated_at' => $pond->updated_at,
            ]);

            DB::table('ponds')->where('id', $pond->id)->update(['branch_id' => $branchId]);
        }

        if (Schema::hasColumn('warehouse_items', 'pond_id')) {
            if (! Schema::hasColumn('warehouse_items', 'branch_id')) {
                Schema::table('warehouse_items', function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                });
            }

            foreach (DB::table('warehouse_items')->whereNull('branch_id')->orderBy('id')->get() as $item) {
                $branchId = DB::table('ponds')->where('id', $item->pond_id)->value('branch_id');
                if ($branchId) {
                    DB::table('warehouse_items')->where('id', $item->id)->update(['branch_id' => $branchId]);
                }
            }

            Schema::table('warehouse_items', function (Blueprint $table) {
                if (Schema::hasColumn('warehouse_items', 'pond_id')) {
                    if ($this->foreignKeyExists('warehouse_items', 'warehouse_items_pond_id_foreign')) {
                        $table->dropForeign(['pond_id']);
                    }
                    if ($this->indexExists('warehouse_items', 'warehouse_items_pond_id_category_name_unique')) {
                        $table->dropUnique(['pond_id', 'category', 'name']);
                    }
                    $table->dropColumn('pond_id');
                }
            });

            if (! $this->indexExists('warehouse_items', 'warehouse_items_branch_id_category_name_unique')) {
                Schema::table('warehouse_items', function (Blueprint $table) {
                    $table->unique(['branch_id', 'category', 'name']);
                });
            }
        }

        if (Schema::hasColumn('feed_types', 'pond_id')) {
            if (! Schema::hasColumn('feed_types', 'branch_id')) {
                Schema::table('feed_types', function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                });
            }

            foreach (DB::table('feed_types')->whereNull('branch_id')->orderBy('id')->get() as $feedType) {
                $branchId = DB::table('ponds')->where('id', $feedType->pond_id)->value('branch_id');
                if ($branchId) {
                    DB::table('feed_types')->where('id', $feedType->id)->update(['branch_id' => $branchId]);
                }
            }

            if (Schema::hasColumn('feed_types', 'pond_id')) {
                if ($this->foreignKeyExists('feed_types', 'feed_types_pond_id_foreign')) {
                    Schema::table('feed_types', function (Blueprint $table) {
                        $table->dropForeign(['pond_id']);
                    });
                }

                if ($this->indexExists('feed_types', 'feed_types_pond_id_name_unique')) {
                    Schema::table('feed_types', function (Blueprint $table) {
                        $table->dropUnique(['pond_id', 'name']);
                    });
                }

                Schema::table('feed_types', function (Blueprint $table) {
                    $table->dropColumn('pond_id');
                });
            }

            if (! $this->indexExists('feed_types', 'feed_types_branch_id_name_unique')) {
                Schema::table('feed_types', function (Blueprint $table) {
                    $table->unique(['branch_id', 'name']);
                });
            }
        }

        if (Schema::hasColumn('expenses', 'pond_id')) {
            if (! Schema::hasColumn('expenses', 'branch_id')) {
                Schema::table('expenses', function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                });
            }

            foreach (DB::table('expenses')->whereNull('branch_id')->orderBy('id')->get() as $expense) {
                $branchId = DB::table('ponds')->where('id', $expense->pond_id)->value('branch_id');
                if ($branchId) {
                    DB::table('expenses')->where('id', $expense->id)->update(['branch_id' => $branchId]);
                }
            }

            Schema::table('expenses', function (Blueprint $table) {
                if (Schema::hasColumn('expenses', 'pond_id')) {
                    if ($this->foreignKeyExists('expenses', 'expenses_pond_id_foreign')) {
                        $table->dropForeign(['pond_id']);
                    }
                    $table->dropColumn('pond_id');
                }
            });
        }

        if (! Schema::hasTable('branch_worker')) {
            Schema::create('branch_worker', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('position')->nullable();
                $table->timestamp('hired_at')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'user_id']);
            });
        }

        if (Schema::hasTable('pond_worker')) {
            $seen = DB::table('branch_worker')
                ->get()
                ->mapWithKeys(fn ($row) => ["{$row->branch_id}:{$row->user_id}" => true])
                ->all();

            foreach (DB::table('pond_worker')->get() as $row) {
                $branchId = DB::table('ponds')->where('id', $row->pond_id)->value('branch_id');
                if (! $branchId) {
                    continue;
                }

                $key = "{$branchId}:{$row->user_id}";
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                DB::table('branch_worker')->insert([
                    'branch_id' => $branchId,
                    'user_id' => $row->user_id,
                    'position' => $row->position,
                    'hired_at' => $row->hired_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ponds', 'branch_id')) {
            Schema::table('ponds', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        Schema::dropIfExists('branch_worker');
        Schema::dropIfExists('branches');
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

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return str_contains($foreignKey, 'pond_id') && Schema::hasColumn($table, 'pond_id');
        }

        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_type = ? AND constraint_name = ?',
            [$database, $table, 'FOREIGN KEY', $foreignKey]
        );

        return (int) ($result[0]->aggregate ?? 0) > 0;
    }
};
