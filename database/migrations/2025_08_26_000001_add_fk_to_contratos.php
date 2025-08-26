<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('contratos', function (Blueprint $table) {
            if (!Schema::hasColumn('contratos', 'fk_cliente')) {
                $table->unsignedBigInteger('fk_cliente')->nullable()->after('id');
                $table->foreign('fk_cliente')
                    ->references('pk_cliente')->on('clientes')
                    ->onUpdate('cascade')->onDelete('restrict');
                $table->index(['fk_cliente', 'fecha_inicio']);
            }

            if (!Schema::hasColumn('contratos', 'fk_propiedad')) {
                $table->unsignedBigInteger('fk_propiedad')->nullable()->after('fk_cliente');
                $table->foreign('fk_propiedad')
                    ->references('pk_propiedad')->on('propiedades')
                    ->onUpdate('cascade')->onDelete('restrict');
                $table->index(['fk_propiedad', 'fecha_inicio']);
            }
        });
    }

    public function down(): void {
        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'fk_cliente')) {
                $table->dropForeign(['fk_cliente']);
                $table->dropIndex(['fk_cliente', 'fecha_inicio']);
                $table->dropColumn('fk_cliente');
            }
            if (Schema::hasColumn('contratos', 'fk_propiedad')) {
                $table->dropForeign(['fk_propiedad']);
                $table->dropIndex(['fk_propiedad', 'fecha_inicio']);
                $table->dropColumn('fk_propiedad');
            }
        });
    }
};
