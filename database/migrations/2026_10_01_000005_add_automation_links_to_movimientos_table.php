<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreignId('source_movimiento_id')->nullable()->after('id')->constrained('movimientos')->nullOnDelete();
            $table->foreignId('contrato_id')->nullable()->after('inquilino_id')->constrained('contratos')->nullOnDelete();
            $table->string('automation_type', 40)->nullable()->after('concepto');
            $table->boolean('auto_generated')->default(false)->after('automation_type');
            $table->unique('source_movimiento_id', 'movimientos_source_movimiento_unique');
            $table->index(['contrato_id', 'automation_type'], 'movimientos_contract_automation_index');
        });
    }
    public function down(): void {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropUnique('movimientos_source_movimiento_unique');
            $table->dropIndex('movimientos_contract_automation_index');
            $table->dropForeign(['source_movimiento_id']);
            $table->dropForeign(['contrato_id']);
            $table->dropColumn(['source_movimiento_id', 'contrato_id', 'automation_type', 'auto_generated']);
        });
    }
};
