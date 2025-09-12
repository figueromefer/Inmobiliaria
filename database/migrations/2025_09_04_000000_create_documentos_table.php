<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id('pk_documento');
            $table->unsignedBigInteger('fk_cliente')->nullable();
            $table->unsignedBigInteger('fk_propiedad')->nullable();
            $table->string('titulo')->nullable();
            $table->string('archivo');
            $table->timestamps();

            $table->foreign('fk_cliente')
                  ->references('pk_cliente')
                  ->on('clientes')
                  ->onDelete('cascade');
            $table->foreign('fk_propiedad')
                  ->references('pk_propiedad')
                  ->on('propiedades')
                  ->onDelete('cascade');
        });
    }

    public function down() {
        Schema::dropIfExists('documentos');
    }
};
