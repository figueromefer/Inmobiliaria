<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_solicitante', 50)->nullable();
            $table->string('tipo_complementaria', 50)->nullable();
            $table->string('tipo_tercero', 50)->nullable();
            $table->string('solicitante')->nullable();
            $table->dateTime('fecha'); // fecha/hora alta (servidor)
            $table->foreignId('inquilino_id')->nullable()
                  ->constrained('inquilinos')->nullOnDelete();
            $table->string('domicilio_inmueble')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->decimal('comision_renta', 12, 2)->nullable();
            $table->decimal('comision_mensual', 12, 2)->nullable();
            $table->unsignedSmallInteger('dias_pago')->nullable();
            $table->decimal('monto_total', 12, 2)->nullable();
            $table->decimal('monto_mensual', 12, 2)->nullable();
            $table->decimal('monto_deposito', 12, 2)->nullable();
            $table->string('edit_url', 512)->nullable(); // opcional
            $table->string('urldoc', 512)->nullable(); // opcional
            $table->timestamps();

            $table->index('inquilino_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
