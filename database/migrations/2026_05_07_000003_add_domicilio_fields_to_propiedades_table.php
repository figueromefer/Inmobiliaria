<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->string('calle')->nullable()->after('domicilio');
            $table->string('numero_exterior')->nullable()->after('calle');
            $table->string('numero_interior')->nullable()->after('numero_exterior');
            $table->string('colonia')->nullable()->after('numero_interior');
            $table->string('codigo_postal', 10)->nullable()->after('colonia');
            $table->string('municipio')->nullable()->after('codigo_postal');
            $table->string('estado')->nullable()->after('municipio');
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropColumn([
                'calle',
                'numero_exterior',
                'numero_interior',
                'colonia',
                'codigo_postal',
                'municipio',
                'estado',
            ]);
        });
    }
};
