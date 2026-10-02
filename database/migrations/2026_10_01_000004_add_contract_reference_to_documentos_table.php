<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('contrato_id')->nullable()->after('fk_inquilino')->constrained('contratos')->nullOnDelete();
            $table->index(['contrato_id', 'tipo'], 'documentos_contrato_tipo_index');
        });
    }
    public function down(): void {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['contrato_id']);
            $table->dropIndex('documentos_contrato_tipo_index');
            $table->dropColumn('contrato_id');
        });
    }
};
