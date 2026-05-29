<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_pendientes', function (Blueprint $table) {
            $table->id();
            $table->string('origen', 50);
            $table->string('external_id')->nullable();
            $table->string('expediente')->nullable();
            $table->string('estado', 50)->default('pendiente_match');
            $table->json('raw_payload')->nullable();
            $table->json('mapped_payload')->nullable();
            $table->foreignId('matched_cliente_id')->nullable()
                ->constrained('clientes', 'pk_cliente')
                ->nullOnDelete();
            $table->foreignId('matched_propiedad_id')->nullable()
                ->constrained('propiedades', 'pk_propiedad')
                ->nullOnDelete();
            $table->foreignId('matched_inquilino_id')->nullable()
                ->constrained('inquilinos')
                ->nullOnDelete();
            $table->foreignId('contrato_id')->nullable()
                ->constrained('contratos')
                ->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'origen']);
            $table->index('expediente');
            $table->unique(['origen', 'external_id'], 'contratos_pendientes_origen_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_pendientes');
    }
};
