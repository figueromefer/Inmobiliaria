<?php

use App\Models\Movimiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('movimientos', 'folio')) {
            Schema::table('movimientos', function (Blueprint $table) {
                $table->string('folio', 20)->nullable()->after('id');
            });
        }

        DB::table('movimientos')
            ->whereNull('folio')
            ->orWhere('folio', '')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(200, function ($movimientos) {
                foreach ($movimientos as $movimiento) {
                    DB::table('movimientos')
                        ->where('id', $movimiento->id)
                        ->where(function ($query) {
                            $query->whereNull('folio')
                                ->orWhere('folio', '');
                        })
                        ->update(['folio' => Movimiento::formatFolio($movimiento->id)]);
                }
            });

        $this->addUniqueIndexIfMissing();
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            if ($this->indexExists('movimientos', 'movimientos_folio_unique')) {
                $table->dropUnique('movimientos_folio_unique');
            }

            if (Schema::hasColumn('movimientos', 'folio')) {
                $table->dropColumn('folio');
            }
        });
    }

    private function addUniqueIndexIfMissing(): void
    {
        if ($this->indexExists('movimientos', 'movimientos_folio_unique')) {
            return;
        }

        Schema::table('movimientos', function (Blueprint $table) {
            $table->unique('folio', 'movimientos_folio_unique');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
