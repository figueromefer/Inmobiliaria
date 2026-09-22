<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50);
            $table->string('external_id')->nullable();
            $table->string('status', 50)->default('draft');
            $table->foreignId('contrato_id')->nullable()
                ->constrained('contratos')
                ->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()
                ->constrained('clientes', 'pk_cliente')
                ->nullOnDelete();
            $table->foreignId('propiedad_id')->nullable()
                ->constrained('propiedades', 'pk_propiedad')
                ->nullOnDelete();
            $table->foreignId('inquilino_id')->nullable()
                ->constrained('inquilinos')
                ->nullOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('published_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            // MySQL permite múltiples NULL en un índice único compuesto.
            $table->unique(['source', 'external_id'], 'contract_drafts_source_external_unique');
            $table->index('current_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_drafts');
    }
};
