<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->date('mantenimiento_fecha_pago')->nullable()->after('mantenimiento_monto');
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropColumn('mantenimiento_fecha_pago');
        });
    }
};
