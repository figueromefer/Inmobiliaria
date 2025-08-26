<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('contratos', function (Blueprint $table) {
            // Si necesitas permitir contratos sin propiedad, cambia a ->nullable()
            $table->unsignedBigInteger('fk_cliente')->nullable(false)->change();
            $table->unsignedBigInteger('fk_propiedad')->nullable(false)->change();

            if (Schema::hasColumn('contratos', 'solicitante')) {
                $table->dropColumn('solicitante');
            }
        });
    }

    public function down(): void {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('solicitante')->nullable();
            $table->unsignedBigInteger('fk_cliente')->nullable()->change();
            $table->unsignedBigInteger('fk_propiedad')->nullable()->change();
        });
    }
};
