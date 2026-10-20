<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farms')) {
            Schema::create('farms', function (Blueprint $table) {
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

        if (! Schema::hasColumn('ponds', 'farm_id')) {
            Schema::table('ponds', function (Blueprint $table) {
                $table->foreignId('farm_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        $directorIds = DB::table('ponds')
            ->whereNotNull('director_id')
            ->distinct()
            ->pluck('director_id');

        foreach ($directorIds as $directorId) {
            $firstPond = DB::table('ponds')
                ->where('director_id', $directorId)
                ->orderBy('id')
                ->first();

            if (! $firstPond) {
                continue;
            }

            $farmId = DB::table('farms')->insertGetId([
                'name' => 'Hojalik',
                'region' => $firstPond->region,
                'director_id' => $directorId,
                'created_by' => $firstPond->created_by,
                'status' => 'active',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ponds')
                ->where('director_id', $directorId)
                ->whereNull('farm_id')
                ->update(['farm_id' => $farmId]);
        }

        foreach (DB::table('ponds')->whereNull('farm_id')->orderBy('id')->get() as $pond) {
            $farmId = DB::table('farms')->insertGetId([
                'name' => $pond->name,
                'region' => $pond->region,
                'director_id' => $pond->director_id,
                'created_by' => $pond->created_by,
                'status' => $pond->status,
                'notes' => $pond->notes,
                'created_at' => $pond->created_at,
                'updated_at' => $pond->updated_at,
            ]);

            DB::table('ponds')->where('id', $pond->id)->update(['farm_id' => $farmId]);
        }

        if (Schema::hasColumn('warehouse_items', 'pond_id')) {
            if (! Schema::hasColumn('warehouse_items', 'farm_id')) {
                Schema::table('warehouse_items', function (Blueprint $table) {
                    $table->foreignId('farm_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                });
            }

            foreach (DB::table('warehouse_items')->whereNull('farm_id')->orderBy('id')->get() as $item) {
                $farmId = DB::table('ponds')->where('id', $item->pond_id)->value('farm_id');
                if (! $farmId) {
                    continue;
                }

                $existingId = DB::table('warehouse_items')
                    ->where('farm_id', $farmId)
                    ->where('category', $item->category)
                    ->where('name', $item->name)
                    ->where('id', '!=', $item->id)
                    ->value('id');

                if ($existingId) {
                    $existingQty = (float) DB::table('warehouse_items')->where('id', $existingId)->value('quantity');
                    DB::table('warehouse_items')
                        ->where('id', $existingId)
                        ->update([
                            'quantity' => round($existingQty + (float) $item->quantity, 2),
                            'updated_at' => now(),
                        ]);
                    DB::table('warehouse_items')->where('id', $item->id)->delete();
                } else {
                    DB::table('warehouse_items')->where('id', $item->id)->update(['farm_id' => $farmId]);
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

            if (! $this->indexExists('warehouse_items', 'warehouse_items_farm_id_category_name_unique')) {
                Schema::table('warehouse_items', function (Blueprint $table) {
                    $table->unique(['farm_id', 'category', 'name']);
                });
            }
        }

        if (Schema::hasColumn('feed_types', 'pond_id')) {
            if (! Schema::hasColumn('feed_types', 'farm_id')) {
                Schema::table('feed_types', function (Blueprint $table) {
                    $table->foreignId('farm_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                });
            }

            foreach (DB::table('feed_types')->whereNull('farm_id')->orderBy('id')->get() as $feedType) {
                $farmId = DB::table('ponds')->where('id', $feedType->pond_id)->value('farm_id');
                if (! $farmId) {
                    continue;
                }

                $exists = DB::table('feed_types')
                    ->where('farm_id', $farmId)
                    ->where('name', $feedType->name)
                    ->where('id', '!=', $feedType->id)
                    ->exists();

                if ($exists) {
                    DB::table('feed_types')->where('id', $feedType->id)->delete();
                } else {
                    DB::table('feed_types')->where('id', $feedType->id)->update(['farm_id' => $farmId]);
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

            if (! $this->indexExists('feed_types', 'feed_types_farm_id_name_unique')) {
                Schema::table('feed_types', function (Blueprint $table) {
                    $table->unique(['farm_id', 'name']);
                });
            }
        }

        if (Schema::hasColumn('expenses', 'pond_id')) {
            if (! Schema::hasColumn('expenses', 'farm_id')) {
                Schema::table('expenses', function (Blueprint $table) {
                    $table->foreignId('farm_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                });
            }

            foreach (DB::table('expenses')->whereNull('farm_id')->orderBy('id')->get() as $expense) {
                $farmId = DB::table('ponds')->where('id', $expense->pond_id)->value('farm_id');
                if ($farmId) {
                    DB::table('expenses')->where('id', $expense->id)->update(['farm_id' => $farmId]);
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

        if (! Schema::hasTable('farm_worker')) {
            Schema::create('farm_worker', function (Blueprint $table) {
                $table->id();
                $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('position')->nullable();
                $table->timestamp('hired_at')->nullable();
                $table->timestamps();

                $table->unique(['farm_id', 'user_id']);
            });
        }

        if (Schema::hasTable('pond_worker')) {
            $seen = DB::table('farm_worker')
                ->get()
                ->mapWithKeys(fn ($row) => ["{$row->farm_id}:{$row->user_id}" => true])
                ->all();

            foreach (DB::table('pond_worker')->get() as $row) {
                $farmId = DB::table('ponds')->where('id', $row->pond_id)->value('farm_id');
                if (! $farmId) {
                    continue;
                }

                $key = "{$farmId}:{$row->user_id}";
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                DB::table('farm_worker')->insert([
                    'farm_id' => $farmId,
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
        if (Schema::hasColumn('ponds', 'farm_id')) {
            Schema::table('ponds', function (Blueprint $table) {
                $table->dropConstrainedForeignId('farm_id');
            });
        }

        Schema::dropIfExists('farm_worker');
        Schema::dropIfExists('farms');
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
