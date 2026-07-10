<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('movimientos', 'asignado_a_tipo')) {
                $table->string('asignado_a_tipo', 30)->default('cliente')->after('id');
            }

            if (! Schema::hasColumn('movimientos', 'inquilino_id')) {
                $table->unsignedBigInteger('inquilino_id')->nullable()->after('propiedad_id');
                $table->foreign('inquilino_id', 'movimientos_inquilino_id_foreign')
                    ->references('id')
                    ->on('inquilinos')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });

        DB::table('movimientos')
            ->whereNull('asignado_a_tipo')
            ->update(['asignado_a_tipo' => 'cliente']);

        DB::table('movimientos')
            ->whereNotNull('propiedad_id')
            ->update(['asignado_a_tipo' => 'propiedad']);

        DB::table('movimientos')
            ->whereNull('propiedad_id')
            ->update(['asignado_a_tipo' => 'cliente']);

        $this->addIndexIfMissing('movimientos', ['asignado_a_tipo'], 'movimientos_asignado_a_tipo_index');
        $this->addIndexIfMissing('movimientos', ['cliente_id'], 'movimientos_cliente_id_index');
        $this->addIndexIfMissing('movimientos', ['propiedad_id'], 'movimientos_propiedad_id_index');
        $this->addIndexIfMissing('movimientos', ['inquilino_id'], 'movimientos_inquilino_id_index');
        $this->addIndexIfMissing('movimientos', ['cliente_id', 'fecha'], 'movimientos_cliente_id_fecha_index');
        $this->addIndexIfMissing('movimientos', ['propiedad_id', 'fecha'], 'movimientos_propiedad_id_fecha_index');
        $this->addIndexIfMissing('movimientos', ['inquilino_id', 'fecha'], 'movimientos_inquilino_id_fecha_index');
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'movimientos_inquilino_id_fecha_index');
            $this->dropIndexIfExists($table, 'movimientos_propiedad_id_fecha_index');
            $this->dropIndexIfExists($table, 'movimientos_cliente_id_fecha_index');
            $this->dropIndexIfExists($table, 'movimientos_inquilino_id_index');
            $this->dropIndexIfExists($table, 'movimientos_propiedad_id_index');
            $this->dropIndexIfExists($table, 'movimientos_cliente_id_index');
            $this->dropIndexIfExists($table, 'movimientos_asignado_a_tipo_index');

            if (Schema::hasColumn('movimientos', 'inquilino_id')) {
                $table->dropForeign('movimientos_inquilino_id_foreign');
                $table->dropColumn('inquilino_id');
            }

            if (Schema::hasColumn('movimientos', 'asignado_a_tipo')) {
                $table->dropColumn('asignado_a_tipo');
            }
        });
    }

    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName) || $this->indexForColumnsExists($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        if ($this->indexExists('movimientos', $indexName)) {
            $table->dropIndex($indexName);
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function indexForColumnsExists(string $table, array $columns): bool
    {
        $database = DB::getDatabaseName();
        $expected = implode(',', $columns);

        $indexes = DB::table('information_schema.statistics')
            ->select('index_name', DB::raw("GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') as columns_list"))
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->groupBy('index_name')
            ->get();

        return $indexes->contains(fn ($index) => $index->columns_list === $expected);
    }
};
