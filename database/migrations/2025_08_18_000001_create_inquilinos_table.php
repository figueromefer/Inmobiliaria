<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquilinos', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AI
            $table->string('nombre');
            $table->string('nacionalidad', 100)->nullable();
            $table->string('domicilio')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 191)->nullable(); // 191 por índices únicos si algún día
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquilinos');
    }
};
