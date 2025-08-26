<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
       Schema::create('movimientos', function (Blueprint $table) {
            $table->id();

            // Debe coincidir con clientes.pk_cliente (BIGINT UNSIGNED)
            $table->unsignedBigInteger('cliente_id');

            // Debe coincidir con propiedades.pk_propiedad (BIGINT UNSIGNED)
            $table->unsignedBigInteger('propiedad_id');

            $table->string('concepto', 50);      // 'deposito','renta','gasto'
            $table->date('fecha');
            $table->decimal('importe', 12, 2);
            $table->string('forma_pago', 30);    // 'efectivo','transferencia'
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['cliente_id','propiedad_id','fecha']);

            $table->foreign('cliente_id')
                ->references('pk_cliente')->on('clientes')
                ->restrictOnDelete()->cascadeOnUpdate();

            $table->foreign('propiedad_id')
                ->references('pk_propiedad')->on('propiedades')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

    }

    public function down(): void {
        Schema::dropIfExists('movimientos');
    }
};
