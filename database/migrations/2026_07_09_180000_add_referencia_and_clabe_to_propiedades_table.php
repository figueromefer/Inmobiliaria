<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            if (! Schema::hasColumn('propiedades', 'referencia')) {
                $table->string('referencia')->nullable()->after('mantenimiento_cuenta');
            }

            if (! Schema::hasColumn('propiedades', 'clabe')) {
                $table->string('clabe', 18)->nullable()->after('referencia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            if (Schema::hasColumn('propiedades', 'clabe')) {
                $table->dropColumn('clabe');
            }

            if (Schema::hasColumn('propiedades', 'referencia')) {
                $table->dropColumn('referencia');
            }
        });
    }
};
