<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->text('domicilio')->nullable()->change();
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->text('domicilio_inmueble')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->string('domicilio')->nullable()->change();
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->string('domicilio_inmueble')->nullable()->change();
        });
    }
};
