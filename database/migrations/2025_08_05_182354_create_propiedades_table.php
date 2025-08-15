<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('propiedades', function (Blueprint $table) {
            $table->id('pk_propiedad'); // id autoincrementable
            $table->unsignedBigInteger('fk_cliente'); // clave foránea

            $table->string('alias')->nullable();
            $table->string('domicilio');
            $table->string('siapa')->nullable();
            $table->string('cfe')->nullable();
            $table->string('predial')->nullable();
            $table->string('mantenimiento_banco')->nullable();
            $table->string('mantenimiento_cuenta')->nullable();
            $table->decimal('mantenimiento_monto', 10, 2)->nullable();
            $table->decimal('latitud', 10, 6)->nullable();
            $table->decimal('longitud', 10, 6)->nullable();

            $table->timestamps();
            // Definimos la relación con la tabla clientes
            $table->foreign('fk_cliente')->references('pk_cliente')->on('clientes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('propiedades');
    }
};
