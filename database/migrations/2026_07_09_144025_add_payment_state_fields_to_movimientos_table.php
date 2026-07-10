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
        Schema::table('movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('movimientos', 'estado_pago')) {
                $table->string('estado_pago', 20)->default(Movimiento::PAYMENT_LIQUIDATED)->after('approval_status');
            }

            if (! Schema::hasColumn('movimientos', 'fecha_liquidacion')) {
                $table->date('fecha_liquidacion')->nullable()->after('estado_pago');
            }

            if (! Schema::hasColumn('movimientos', 'afecta_saldo_cliente')) {
                $table->boolean('afecta_saldo_cliente')->default(true)->after('fecha_liquidacion');
            }
        });

        DB::table('movimientos')
            ->where('approval_status', Movimiento::STATUS_APPROVED)
            ->update([
                'estado_pago' => Movimiento::PAYMENT_LIQUIDATED,
                'afecta_saldo_cliente' => true,
            ]);

        DB::table('movimientos')
            ->where('approval_status', Movimiento::STATUS_PENDING)
            ->update([
                'estado_pago' => Movimiento::PAYMENT_PENDING,
                'fecha_liquidacion' => null,
                'afecta_saldo_cliente' => true,
            ]);

        DB::table('movimientos')
            ->where('approval_status', Movimiento::STATUS_REJECTED)
            ->update([
                'estado_pago' => Movimiento::PAYMENT_CANCELED,
                'fecha_liquidacion' => null,
                'afecta_saldo_cliente' => true,
            ]);

        DB::table('movimientos')
            ->where('estado_pago', Movimiento::PAYMENT_LIQUIDATED)
            ->whereNull('fecha_liquidacion')
            ->update(['fecha_liquidacion' => DB::raw('fecha')]);

        DB::table('movimientos')
            ->whereNull('afecta_saldo_cliente')
            ->update(['afecta_saldo_cliente' => true]);

        $this->addIndexIfMissing('movimientos', ['estado_pago'], 'movimientos_estado_pago_index');
        $this->addIndexIfMissing('movimientos', ['fecha_liquidacion'], 'movimientos_fecha_liquidacion_index');
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'movimientos_fecha_liquidacion_index');
            $this->dropIndexIfExists($table, 'movimientos_estado_pago_index');

            if (Schema::hasColumn('movimientos', 'afecta_saldo_cliente')) {
                $table->dropColumn('afecta_saldo_cliente');
            }

            if (Schema::hasColumn('movimientos', 'fecha_liquidacion')) {
                $table->dropColumn('fecha_liquidacion');
            }

            if (Schema::hasColumn('movimientos', 'estado_pago')) {
                $table->dropColumn('estado_pago');
            }
        });
    }

    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
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
};
